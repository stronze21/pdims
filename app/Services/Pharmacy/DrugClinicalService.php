<?php

namespace App\Services\Pharmacy;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Local, explicitly reviewed knowledge. Never changes orders, stock, or equivalence. */
class DrugClinicalService
{
    public function __construct(private readonly mixed $connection = null) {}

    private function db()
    {
        return $this->connection ?? DB::connection('hospital');
    }

    public function decode(object $row): object
    {
        $row->data = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);

        return $row;
    }

    public function published(?string $kind = null)
    {
        return $this->db()->table('pharm_clinical_records')->where('status', 'published')
            ->when($kind, fn ($q) => $q->where('kind', $kind))->get()->map(fn ($r) => $this->decode($r));
    }

    public function createIngredient(string $name, string $actor): int
    {
        $name = mb_strtolower(trim($name));
        Validator::make(['name' => $name], ['name' => 'required|string|max:180'])->validate();
        if ($this->db()->table('pharm_clinical_ingredients')->where('name', $name)->exists()) {
            throw ValidationException::withMessages(['ingredientName' => 'This clinical ingredient already exists.']);
        }

        return (int) $this->db()->table('pharm_clinical_ingredients')->insertGetId([
            'name' => $name, 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function saveDraft(string $kind, array $data, string $source, string $reviewedOn, string $actor, ?int $existingId = null): int
    {
        Validator::make(compact('kind', 'source', 'reviewedOn'), [
            'kind' => ['required', Rule::in(['mapping', 'side_effect', 'alternative', 'interaction'])],
            'source' => 'required|string|max:4000', 'reviewedOn' => 'required|date_format:Y-m-d|before_or_equal:today',
        ])->validate();
        $data = $this->validateData($kind, $data);

        return $this->db()->transaction(function () use ($kind, $data, $source, $reviewedOn, $actor, $existingId) {
            $key = $existingId ? $this->db()->table('pharm_clinical_records')->where('id', $existingId)->value('record_key') : null;
            if ($existingId && ! $key) {
                throw ValidationException::withMessages(['record' => 'The clinical record no longer exists.']);
            }
            if ($existingId && $this->db()->table('pharm_clinical_records')->where('id', $existingId)->value('kind') !== $kind) {
                throw ValidationException::withMessages(['kind' => 'A revision must retain its record type.']);
            }
            $naturalKey = match ($kind) {
                'mapping' => 'mapping:'.$data['dmdcomb'].'|'.$data['dmdctr'],
                'interaction' => 'interaction:'.$data['ingredient_a'].'|'.$data['ingredient_b'].'|'.($data['route_a'] ?? '').'|'.($data['route_b'] ?? ''),
                default => null,
            };
            if ($key && $naturalKey && $key !== $naturalKey) {
                throw ValidationException::withMessages(['record' => 'Create a new record when changing the mapped product or interaction pair/routes.']);
            }
            $key = $key ?? $naturalKey ?? $kind.':'.(string) \Illuminate\Support\Str::uuid();
            $revision = 1 + (int) $this->db()->table('pharm_clinical_records')->where('record_key', $key)->lockForUpdate()->max('revision');
            $id = (int) $this->db()->table('pharm_clinical_records')->insertGetId([
                'kind' => $kind, 'record_key' => $key, 'revision' => $revision, 'status' => 'draft',
                'payload' => json_encode($data, JSON_THROW_ON_ERROR), 'source' => trim($source), 'reviewed_on' => $reviewedOn,
                'author' => $actor, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit($id, 'draft_created', $actor);

            return $id;
        });
    }

    private function validateData(string $kind, array $data): array
    {
        $product = ['dmdcomb' => 'required|string|max:12', 'dmdctr' => 'required|integer|min:0|max:255'];
        $rules = match ($kind) {
            'mapping' => $product + ['ingredients' => 'required|array|min:1', 'ingredients.*' => 'required|integer|distinct'],
            'side_effect' => ['ingredient_id' => 'nullable|integer', 'dmdcomb' => 'nullable|string|max:12', 'dmdctr' => 'nullable|integer|min:0|max:255',
                'effect' => 'required|string|max:2000', 'frequency' => 'nullable|string|max:120',
                'serious' => 'required|boolean', 'advice' => 'required|string|max:4000', 'route' => 'nullable|string|max:20', 'form' => 'nullable|string|max:20'],
            'alternative' => $product + ['target_dmdcomb' => 'required|string|max:12', 'target_dmdctr' => 'required|integer|min:0|max:255',
                'indication' => 'required|string|max:2000', 'conditions' => 'required|string|max:4000',
                'dose_notes' => 'required|string|max:4000', 'monitoring' => 'required|string|max:4000', 'authorization' => 'required|string|max:4000'],
            'interaction' => ['ingredient_a' => 'required|integer', 'ingredient_b' => 'required|integer',
                'severity' => ['required', Rule::in(['minor', 'moderate', 'major', 'contraindicated'])],
                'effect' => 'required|string|max:4000', 'advice' => 'required|string|max:4000',
                'route_a' => 'nullable|string|max:20', 'route_b' => 'nullable|string|max:20'],
        };
        $data = Validator::make($data, $rules)->validate();
        if ($kind === 'side_effect' && (empty($data['ingredient_id']) === empty($data['dmdcomb']))) {
            throw ValidationException::withMessages(['ingredient_id' => 'Choose either an ingredient or a product for a side effect.']);
        }
        if ($kind === 'side_effect' && ! empty($data['dmdcomb']) && ! isset($data['dmdctr'])) {
            throw ValidationException::withMessages(['dmdctr' => 'Both product codes are required.']);
        }
        foreach (['dmdcomb' => 'dmdctr', 'target_dmdcomb' => 'target_dmdctr'] as $comb => $ctr) {
            if (! empty($data[$comb]) && ! $this->db()->table('hdmhdr')->where('dmdcomb', $data[$comb])->where('dmdctr', $data[$ctr])->exists()) {
                throw ValidationException::withMessages([$comb => 'Select an existing drug product.']);
            }
        }
        $ids = match ($kind) {
            'mapping' => $data['ingredients'], 'side_effect' => array_filter([$data['ingredient_id'] ?? null]),
            'interaction' => [$data['ingredient_a'], $data['ingredient_b']], default => [],
        };
        foreach ($ids as $id) {
            if (! $this->db()->table('pharm_clinical_ingredients')->where('id', $id)->exists()) {
                throw ValidationException::withMessages(['ingredients' => 'Select existing clinical ingredients.']);
            }
        }
        if ($kind === 'alternative' && $data['dmdcomb'] === $data['target_dmdcomb'] && (string) $data['dmdctr'] === (string) $data['target_dmdctr']) {
            throw ValidationException::withMessages(['target_dmdcomb' => 'An alternative must be a different product.']);
        }
        if ($kind === 'interaction' && $data['ingredient_a'] > $data['ingredient_b']) {
            [$data['ingredient_a'], $data['ingredient_b']] = [$data['ingredient_b'], $data['ingredient_a']];
            [$data['route_a'], $data['route_b']] = [$data['route_b'] ?? null, $data['route_a'] ?? null];
        }

        return $data;
    }

    public function transition(int $id, string $action, string $actor): void
    {
        abort_unless(in_array($action, ['publish', 'retire'], true), 422);
        $this->db()->transaction(function () use ($id, $action, $actor) {
            $row = $this->db()->table('pharm_clinical_records')->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            if ($action === 'publish') {
                if ($row->status !== 'draft') {
                    throw ValidationException::withMessages(['record' => 'Only a draft can be published.']);
                }
                $this->validateData($row->kind, json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR));
                $siblings = $this->db()->table('pharm_clinical_records')->where('record_key', $row->record_key)->lockForUpdate()->get();
                if ($siblings->contains(fn ($r) => $r->revision > $row->revision && $r->published_at)) {
                    throw ValidationException::withMessages(['record' => 'A newer revision has already been published.']);
                }
                foreach ($siblings->where('status', 'published') as $previous) {
                    $this->db()->table('pharm_clinical_records')->where('id', $previous->id)->update(['status' => 'retired', 'updated_at' => now()]);
                    $this->audit($previous->id, 'superseded', $actor);
                }
            }
            $this->db()->table('pharm_clinical_records')->where('id', $id)->update($action === 'publish'
                ? ['status' => 'published', 'approved_by' => $actor, 'published_at' => now(), 'updated_at' => now()]
                : ['status' => 'retired', 'updated_at' => now()]);
            $this->audit($id, $action, $actor);
        });
    }

    private function audit(int $id, string $action, string $actor): void
    {
        $this->db()->table('pharm_clinical_audits')->insert(['record_id' => $id, 'action' => $action, 'actor' => $actor, 'created_at' => now()]);
    }

    public static function productKey(array $product): string
    {
        return $product['dmdcomb'].'|'.$product['dmdctr'];
    }

    /** Pure evaluator: input products carry verified ingredient IDs; output is advisory. */
    public static function evaluate(array $products, array $rules): array
    {
        $alerts = [];
        $products = array_values($products);
        foreach ($products as $i => $left) {
            foreach (array_slice($products, $i + 1) as $right) {
                if (self::productKey($left) === self::productKey($right)) {
                    continue;
                }
                foreach ($rules as $rule) {
                    $r = $rule['data'];
                    $match = static fn ($a, $b) => in_array((int) $r['ingredient_a'], $a['ingredients'], true)
                        && in_array((int) $r['ingredient_b'], $b['ingredients'], true)
                        && (empty($r['route_a']) || ($a['rtecode'] ?? '') === $r['route_a'])
                        && (empty($r['route_b']) || ($b['rtecode'] ?? '') === $r['route_b']);
                    if (! $match($left, $right) && ! $match($right, $left)) {
                        continue;
                    }
                    $pair = [self::productKey($left), self::productKey($right)];
                    sort($pair);
                    $alerts[implode(':', $pair).':'.$rule['id']] = $rule + ['left' => $left, 'right' => $right];
                }
            }
        }
        $rank = ['contraindicated' => 0, 'major' => 1, 'moderate' => 2, 'minor' => 3];
        usort($alerts, fn ($a, $b) => $rank[$a['data']['severity']] <=> $rank[$b['data']['severity']]);

        return $alerts;
    }

    public function checkEncounter(string $encounter, array $selected = []): array
    {
        try {
            // U/P/S are the existing pending/charged/issued dispensing states. Other states are excluded.
            $orders = $this->db()->table('hrxo')->where(fn ($q) => $q->where('enccode', $encounter)->orWhere('original_enccode', $encounter))
                ->whereIn('estatus', ['U', 'P', 'S'])->where(fn ($q) => $q->whereNull('rxostatus')->orWhereNotIn('rxostatus', ['I', 'D', 'C']))
                ->when($this->db()->getSchemaBuilder()->hasColumn('hrxo', 'uddds_end_date'), fn ($q) => $q->where(fn ($q) => $q->whereNull('uddds_end_date')->orWhere('uddds_end_date', '>=', now()->toDateString())))
                ->get(['dmdcomb', 'dmdctr', 'ordcon']);
            $products = $orders->reject(fn ($r) => in_array(strtoupper(trim((string) $r->ordcon)), ['DC', 'DISCO', 'DISC', 'STOP', 'STOPPED', 'DISCONTINUED', 'CANCEL', 'CANCELLED', 'CANCELED', 'COMP', 'COMPLETED'], true))
                ->map(fn ($r) => ['dmdcomb' => $r->dmdcomb, 'dmdctr' => $r->dmdctr, 'uncertain' => true])->all();
            $products = collect(array_merge($products, $selected))->unique(fn ($p) => self::productKey($p))->values();
            $records = $this->published();
            $mappings = $records->where('kind', 'mapping')->keyBy(fn ($r) => self::productKey($r->data));
            $keys = $products->pluck('dmdcomb')->unique()->all();
            $masters = $keys ? $this->db()->table('hdmhdr')->whereIn('dmdcomb', $keys)->get()->keyBy(fn ($r) => $r->dmdcomb.'|'.$r->dmdctr) : collect();
            $unmapped = [];
            $products = $products->map(function ($p) use ($mappings, $masters, &$unmapped) {
                $key = self::productKey($p);
                $master = $masters->get($key);
                $p['label'] = $master->drug_concat ?? $key;
                $p['rtecode'] = $master->rtecode ?? null;
                $p['ingredients'] = array_map('intval', $mappings->get($key)->data['ingredients'] ?? []);
                if (! $p['ingredients']) {
                    $unmapped[] = $p['label'];
                }

                return $p;
            });

            return ['available' => true, 'alerts' => self::evaluate($products->all(), $records->where('kind', 'interaction')->map(fn ($r) => (array) $r)->all()),
                'unmapped' => $unmapped, 'products' => $products->all(), 'uncertain' => $orders->isNotEmpty()];
        } catch (\Throwable $e) {
            report($e);

            return ['available' => false, 'alerts' => [], 'unmapped' => [], 'products' => [], 'uncertain' => false];
        }
    }

    public function details(string $comb, string|int $ctr, ?string $location = null): array
    {
        $drug = $this->db()->table('hdmhdr')->where('dmdcomb', $comb)->where('dmdctr', $ctr)->first();
        abort_unless($drug, 404);
        $records = $this->published();
        $mapping = $records->first(fn ($r) => $r->kind === 'mapping' && self::productKey($r->data) === $comb.'|'.$ctr);
        $ingredients = array_map('intval', $mapping->data['ingredients'] ?? []);
        $effects = $records->filter(function ($r) use ($comb, $ctr, $ingredients, $drug) {
            if ($r->kind !== 'side_effect') {
                return false;
            }
            $d = $r->data;

            return ((! empty($d['ingredient_id']) && in_array((int) $d['ingredient_id'], $ingredients, true))
                || (($d['dmdcomb'] ?? '') === $comb && (string) ($d['dmdctr'] ?? '') === (string) $ctr))
                && (empty($d['route']) || $d['route'] === $drug->rtecode)
                && (empty($d['form']) || $d['form'] === $drug->formcode);
        })->sortByDesc(fn ($r) => $r->data['serious'])->values();
        $alternatives = $records->filter(fn ($r) => $r->kind === 'alternative' && self::productKey($r->data) === $comb.'|'.$ctr)->values();
        foreach ($alternatives as $alternative) {
            $d = $alternative->data;
            $alternative->target = $this->db()->table('hdmhdr')->where('dmdcomb', $d['target_dmdcomb'])->where('dmdctr', $d['target_dmdctr'])->first();
            $alternative->stock = $location && $alternative->target && $alternative->target->dmdstat === 'A' ? $this->db()->table('pharm_drug_stocks')->where('dmdcomb', $d['target_dmdcomb'])
                ->where('dmdctr', $d['target_dmdctr'])->where('loc_code', $location)->where('stock_bal', '>', 0)->where('exp_date', '>', now())->sum('stock_bal') : ($location ? 0 : null);
        }
        $interactions = $records->filter(fn ($r) => $r->kind === 'interaction' && (in_array((int) $r->data['ingredient_a'], $ingredients, true) || in_array((int) $r->data['ingredient_b'], $ingredients, true)))->values();
        $availableStock = $location ? $this->db()->table('pharm_drug_stocks')->where('dmdcomb', $comb)->where('dmdctr', $ctr)
            ->where('loc_code', $location)->where('stock_bal', '>', 0)->where('exp_date', '>', now())->sum('stock_bal') : null;

        return compact('drug', 'mapping', 'effects', 'alternatives', 'interactions', 'availableStock');
    }
}
