<?php

namespace App\Services\Pharmacy;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DrugEquivalenceService
{
    public function __construct(private readonly mixed $connection = null) {}

    private function db(): ConnectionInterface
    {
        return $this->connection ?? DB::connection('hospital');
    }

    public function groupFor(string $dmdcomb, string|int $dmdctr, bool $activeOnly = true): ?object
    {
        return $this->db()->table('pharm_drug_equivalence_members as member')
            ->join('pharm_drug_equivalence_groups as eq', 'eq.id', '=', 'member.group_id')
            ->where('member.dmdcomb', $dmdcomb)->where('member.dmdctr', $dmdctr)
            ->when($activeOnly, fn ($query) => $query->where('eq.status', 'A'))
            ->select('eq.*')->first();
    }

    public function members(int $groupId, bool $activeDrugsOnly = true): Collection
    {
        return $this->db()->table('pharm_drug_equivalence_members as member')
            ->join('hdmhdr as drug', function ($join) {
                $join->on('drug.dmdcomb', '=', 'member.dmdcomb')->on('drug.dmdctr', '=', 'member.dmdctr');
            })
            ->where('member.group_id', $groupId)
            ->when($activeDrugsOnly, fn ($query) => $query->where('drug.dmdstat', 'A'))
            ->select('drug.*', 'member.group_id', 'member.created_at as member_since')->get();
    }

    public function substitutionCandidates(string $dmdcomb, string|int $dmdctr, string $location): Collection
    {
        $group = $this->groupFor($dmdcomb, $dmdctr);
        if (! $group) {
            return collect();
        }

        $description = DrugDescription::fromMaster('drug', $this->db()->getDriverName());

        return $this->db()->table('pharm_drug_equivalence_members as member')
            ->join('hdmhdr as drug', function ($join) {
                $join->on('drug.dmdcomb', '=', 'member.dmdcomb')->on('drug.dmdctr', '=', 'member.dmdctr');
            })
            ->join('pharm_drug_stocks as stock', function ($join) use ($location) {
                $join->on('stock.dmdcomb', '=', 'member.dmdcomb')->on('stock.dmdctr', '=', 'member.dmdctr')
                    ->where('stock.loc_code', '=', $location)->where('stock.stock_bal', '>', 0)
                    ->where('stock.exp_date', '>', now());
            })
            ->where('member.group_id', $group->id)->where('drug.dmdstat', 'A')
            ->select('drug.dmdcomb', 'drug.dmdctr', 'drug.brandname')->selectRaw("{$description} AS drug_concat")
            ->selectRaw('SUM(stock.stock_bal) as total_stock')
            ->groupBy('drug.dmdcomb', 'drug.dmdctr', 'drug.brandname', 'drug.grpcode', 'drug.dmdnost',
                'drug.dmdnnostp', 'drug.strecode', 'drug.formcode', 'drug.rtecode')
            ->orderByRaw('CASE WHEN drug.dmdcomb = ? AND drug.dmdctr = ? THEN 0 ELSE 1 END', [$dmdcomb, $dmdctr])
            ->orderByRaw($description)->get();
    }

    public function create(string $dmdcomb, string|int $dmdctr, ?string $actor): int
    {
        return $this->db()->transaction(function () use ($dmdcomb, $dmdctr, $actor) {
            $drug = $this->drug($dmdcomb, $dmdctr, true);
            if ($this->groupFor($dmdcomb, $dmdctr, false)) {
                throw ValidationException::withMessages(['equivalence' => 'This drug already belongs to an equivalence group.']);
            }

            $groupId = (int) $this->db()->table('pharm_drug_equivalence_groups')->insertGetId([
                'canonical_dmdcomb' => $drug->dmdcomb, 'canonical_dmdctr' => $drug->dmdctr,
                'status' => 'A', 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->db()->table('pharm_drug_equivalence_members')->insert([
                'group_id' => $groupId, 'dmdcomb' => $drug->dmdcomb, 'dmdctr' => $drug->dmdctr,
                'added_by' => $actor, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit($groupId, 'created', $drug->dmdcomb, $drug->dmdctr, $actor);

            return $groupId;
        });
    }

    public function addMember(int $groupId, string $dmdcomb, string|int $dmdctr, ?string $actor): void
    {
        $this->db()->transaction(function () use ($groupId, $dmdcomb, $dmdctr, $actor) {
            $group = $this->lockedGroup($groupId);
            $canonical = $this->drug($group->canonical_dmdcomb, $group->canonical_dmdctr, true);
            $candidate = $this->drug($dmdcomb, $dmdctr, true);
            if ($this->fingerprint($canonical) !== $this->fingerprint($candidate)) {
                throw ValidationException::withMessages(['equivalence' => 'Generic ingredient, dose, strength, form, and route must match the canonical drug exactly.']);
            }
            if ($this->groupFor($dmdcomb, $dmdctr, false)) {
                throw ValidationException::withMessages(['equivalence' => 'This drug already belongs to an equivalence group.']);
            }
            $this->db()->table('pharm_drug_equivalence_members')->insert([
                'group_id' => $groupId, 'dmdcomb' => $candidate->dmdcomb, 'dmdctr' => $candidate->dmdctr,
                'added_by' => $actor, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit($groupId, 'member_added', $candidate->dmdcomb, $candidate->dmdctr, $actor);
        });
    }

    public function removeMember(int $groupId, string $dmdcomb, string|int $dmdctr, ?string $actor): void
    {
        $this->db()->transaction(function () use ($groupId, $dmdcomb, $dmdctr, $actor) {
            $group = $this->lockedGroup($groupId);
            if ($group->canonical_dmdcomb === $dmdcomb && (string) $group->canonical_dmdctr === (string) $dmdctr) {
                throw ValidationException::withMessages(['equivalence' => 'Choose another canonical drug before removing this member.']);
            }
            $this->db()->table('pharm_drug_equivalence_members')->where('group_id', $groupId)
                ->where('dmdcomb', $dmdcomb)->where('dmdctr', $dmdctr)->delete();
            $this->audit($groupId, 'member_removed', $dmdcomb, $dmdctr, $actor);
        });
    }

    public function setCanonical(int $groupId, string $dmdcomb, string|int $dmdctr, ?string $actor): void
    {
        $this->db()->transaction(function () use ($groupId, $dmdcomb, $dmdctr, $actor) {
            $this->lockedGroup($groupId);
            $exists = $this->db()->table('pharm_drug_equivalence_members')->where('group_id', $groupId)
                ->where('dmdcomb', $dmdcomb)->where('dmdctr', $dmdctr)->exists();
            if (! $exists) {
                throw ValidationException::withMessages(['equivalence' => 'The canonical drug must be a member of this group.']);
            }
            $this->db()->table('pharm_drug_equivalence_groups')->where('id', $groupId)->update([
                'canonical_dmdcomb' => $dmdcomb, 'canonical_dmdctr' => $dmdctr, 'updated_at' => now(),
            ]);
            $this->audit($groupId, 'canonical_changed', $dmdcomb, $dmdctr, $actor);
        });
    }

    public function toggle(int $groupId, ?string $actor): void
    {
        $this->db()->transaction(function () use ($groupId, $actor) {
            $group = $this->lockedGroup($groupId);
            $status = $group->status === 'A' ? 'I' : 'A';
            $this->db()->table('pharm_drug_equivalence_groups')->where('id', $groupId)
                ->update(['status' => $status, 'updated_at' => now()]);
            $this->audit($groupId, $status === 'A' ? 'reactivated' : 'deactivated', null, null, $actor);
        });
    }

    public function suggestions(int $groupId, string $search = ''): array
    {
        $group = $this->lockedGroup($groupId);
        $canonical = $this->drug($group->canonical_dmdcomb, $group->canonical_dmdctr, true);
        $all = $this->drugQuery()->where('drug.dmdstat', 'A')
            ->when($search, fn ($q) => $q->whereRaw(DrugDescription::fromMaster('drug', $this->db()->getDriverName()).' like ?', ['%'.$search.'%']))
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('pharm_drug_equivalence_members as used')
                    ->whereColumn('used.dmdcomb', 'drug.dmdcomb')->whereColumn('used.dmdctr', 'drug.dmdctr');
            })->limit(100)->get();
        [$compatible, $conflicts] = $all->partition(fn ($drug) => $this->fingerprint($canonical) === $this->fingerprint($drug));
        $needle = $this->normalizeDescription($canonical->drug_concat);
        $compatible = $compatible->sortByDesc(fn ($drug) => $this->similarity($needle, $this->normalizeDescription($drug->drug_concat)))->values();

        return ['compatible' => $compatible, 'conflicts' => $conflicts->take(10)->values()];
    }

    public function possibleGroups(string $search = '', int $limit = 50): Collection
    {
        $drugs = $this->drugQuery()->where('drug.dmdstat', 'A')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('pharm_drug_equivalence_members as used')
                    ->whereColumn('used.dmdcomb', 'drug.dmdcomb')->whereColumn('used.dmdctr', 'drug.dmdctr');
            })
            ->orderByRaw(DrugDescription::fromMaster('drug', $this->db()->getDriverName()))->get();

        $needle = $this->normalizeDescription($search);

        return $drugs->groupBy(fn ($drug) => $this->fingerprint($drug))
            ->filter(fn (Collection $members) => $members->count() > 1)
            ->map(function (Collection $members, string $fingerprint) {
                $canonical = $members->first();

                return (object) [
                    'fingerprint' => hash('sha256', $fingerprint),
                    'canonical' => $canonical,
                    'members' => $members->values(),
                    'member_count' => $members->count(),
                ];
            })
            ->when($needle !== '', fn (Collection $groups) => $groups->filter(function ($group) use ($needle) {
                return $group->members->contains(function ($drug) use ($needle) {
                    return str_contains($this->normalizeDescription($drug->drug_concat), $needle)
                        || str_contains(strtolower((string) $drug->dmdcomb), strtolower($needle));
                });
            }))
            ->sortByDesc(fn ($group) => $group->member_count)
            ->take($limit)->values();
    }

    public function potentialMembersFor(string $dmdcomb, string|int $dmdctr): Collection
    {
        $canonical = $this->drug($dmdcomb, $dmdctr, true);
        if ($this->groupFor($dmdcomb, $dmdctr, false)) {
            throw ValidationException::withMessages(['equivalence' => 'This drug already belongs to an equivalence group.']);
        }

        return $this->drugQuery()->where('drug.dmdstat', 'A')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('pharm_drug_equivalence_members as used')
                    ->whereColumn('used.dmdcomb', 'drug.dmdcomb')->whereColumn('used.dmdctr', 'drug.dmdctr');
            })->get()->filter(fn ($drug) => $this->fingerprint($canonical) === $this->fingerprint($drug))
            ->sortByDesc(fn ($drug) => $this->similarity(
                $this->normalizeDescription($canonical->drug_concat),
                $this->normalizeDescription($drug->drug_concat)
            ))->values();
    }

    public function createReviewed(string $canonicalDmdcomb, string|int $canonicalDmdctr, array $members, ?string $actor): int
    {
        $selected = collect($members)->map(fn ($member) => [
            'dmdcomb' => (string) ($member['dmdcomb'] ?? ''),
            'dmdctr' => (string) ($member['dmdctr'] ?? ''),
        ])->unique(fn ($member) => $member['dmdcomb'].'|'.$member['dmdctr'])->values();

        if ($selected->count() < 2 || ! $selected->contains(fn ($member) =>
            $member['dmdcomb'] === $canonicalDmdcomb && $member['dmdctr'] === (string) $canonicalDmdctr)) {
            throw ValidationException::withMessages(['equivalence' => 'Select the canonical drug and at least one equivalent member.']);
        }

        return $this->db()->transaction(function () use ($canonicalDmdcomb, $canonicalDmdctr, $selected, $actor) {
            $groupId = $this->create($canonicalDmdcomb, $canonicalDmdctr, $actor);
            foreach ($selected as $member) {
                if ($member['dmdcomb'] === $canonicalDmdcomb && $member['dmdctr'] === (string) $canonicalDmdctr) {
                    continue;
                }
                $this->addMember($groupId, $member['dmdcomb'], $member['dmdctr'], $actor);
            }
            $this->audit($groupId, 'review_approved', null, null, $actor);

            return $groupId;
        });
    }

    public function dissolve(int $groupId, ?string $actor): void
    {
        $this->db()->transaction(function () use ($groupId, $actor) {
            $this->lockedGroup($groupId);
            $this->audit($groupId, 'dissolved', null, null, $actor);
            $this->db()->table('pharm_drug_equivalence_members')->where('group_id', $groupId)->delete();
            $this->db()->table('pharm_drug_equivalence_groups')->where('id', $groupId)
                ->update(['status' => 'D', 'updated_at' => now()]);
        });
    }

    public function fingerprint(object $drug): string
    {
        return implode('|', [(string) $drug->gencode, number_format((float) $drug->dmdnost, 4, '.', ''),
            (string) $drug->dmdnnostp, (string) $drug->strecode, (string) $drug->formcode, (string) $drug->rtecode]);
    }

    public function normalizeDescription(?string $description): string
    {
        $value = strtolower((string) $description);
        $value = preg_replace('/\b(tab|tabs)\b/', 'tablet', $value);
        $value = preg_replace('/\b(cap|caps)\b/', 'capsule', $value);
        $value = preg_replace('/\b(inj)\b/', 'injection', $value);
        $value = preg_replace('/\b(susp)\b/', 'suspension', $value);
        return trim(preg_replace('/[^a-z0-9%]+/', ' ', $value));
    }

    private function similarity(string $left, string $right): float
    {
        similar_text($left, $right, $percent);
        return $percent;
    }

    private function drug(string $dmdcomb, string|int $dmdctr, bool $fail = false): ?object
    {
        $drug = $this->drugQuery()->where('drug.dmdcomb', $dmdcomb)->where('drug.dmdctr', $dmdctr)->first();
        if ($fail && ! $drug) {
            throw ValidationException::withMessages(['equivalence' => 'The selected drug no longer exists.']);
        }
        return $drug;
    }

    private function drugQuery()
    {
        return $this->db()->table('hdmhdr as drug')->join('hdruggrp as grp', 'grp.grpcode', '=', 'drug.grpcode')
            ->select('drug.*', 'grp.gencode')->selectRaw(DrugDescription::fromMaster('drug', $this->db()->getDriverName()).' AS drug_concat');
    }

    private function lockedGroup(int $groupId): object
    {
        $group = $this->db()->table('pharm_drug_equivalence_groups')->where('id', $groupId)->lockForUpdate()->first();
        if (! $group) {
            throw ValidationException::withMessages(['equivalence' => 'The equivalence group no longer exists.']);
        }
        return $group;
    }

    private function audit(int $groupId, string $action, ?string $dmdcomb, string|int|null $dmdctr, ?string $actor): void
    {
        $this->db()->table('pharm_drug_equivalence_audits')->insert([
            'group_id' => $groupId, 'action' => $action, 'dmdcomb' => $dmdcomb, 'dmdctr' => $dmdctr,
            'actor' => $actor, 'details_json' => null, 'created_at' => now(),
        ]);
    }
}
