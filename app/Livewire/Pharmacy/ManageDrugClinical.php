<?php

namespace App\Livewire\Pharmacy;

use App\Services\Pharmacy\DrugClinicalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class ManageDrugClinical extends Component
{
    use WithPagination;

    public string $kind = 'side_effect';

    public string $search = '';

    public string $status = '';

    public string $productSearch = '';

    public string $product = '';

    public string $ingredientName = '';

    public array $form = [];

    public string $source = '';

    public string $reviewedOn = '';

    #[Locked]
    public ?int $editingId = null;

    public string $notice = '';

    public function mount(): void
    {
        $this->authorizeView();
        $this->reviewedOn = now()->toDateString();
    }

    private function authorizeView(): void
    {
        abort_unless(auth()->user()?->can('view-drug-clinical'), 403);
    }

    public function updatedKind(): void
    {
        $this->resetEditor();
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function resetEditor(): void
    {
        $this->reset('editingId', 'form', 'source', 'notice');
        $this->reviewedOn = now()->toDateString();
        $this->resetValidation();
    }

    public static function fields(string $kind): array
    {
        return match ($kind) {
            'mapping' => ['product' => 'Drug product', 'ingredients' => 'Verified ingredients'],
            'side_effect' => ['ingredient_id' => 'Ingredient (leave blank for product-specific notes)', 'product' => 'Product (leave blank for ingredient-level effects)',
                'effect' => 'Side effect', 'frequency' => 'Frequency (if known)', 'serious' => 'Serious effect', 'advice' => 'Counseling and recommended action', 'route' => 'Route restriction (optional)', 'form' => 'Form restriction (optional)'],
            'alternative' => ['product' => 'Prescribed drug', 'target_product' => 'Therapeutic alternative', 'indication' => 'Indication', 'conditions' => 'Conditions for use',
                'dose_notes' => 'Dose and route considerations', 'monitoring' => 'Monitoring', 'authorization' => 'Prescriber authorization or hospital protocol guidance'],
            'interaction' => ['ingredient_a' => 'First ingredient', 'ingredient_b' => 'Second ingredient', 'severity' => 'Severity', 'effect' => 'Clinical effect',
                'advice' => 'Management advice', 'route_a' => 'First ingredient route restriction (optional)', 'route_b' => 'Second ingredient route restriction (optional)'],
            default => [],
        };
    }

    public function addIngredient(): void
    {
        abort_unless(auth()->user()?->can('edit-drug-clinical'), 403);
        app(DrugClinicalService::class)->createIngredient($this->ingredientName, (string) auth()->id());
        $this->ingredientName = '';
        $this->notice = 'Ingredient added. Product mappings still require review and publication.';
    }

    public function edit(int $id): void
    {
        $this->authorizeView();
        abort_unless(auth()->user()?->can('edit-drug-clinical'), 403);
        $record = DB::connection('hospital')->table('pharm_clinical_records')->find($id);
        abort_unless($record, 404);
        $this->kind = $record->kind;
        $this->editingId = $id;
        $this->form = json_decode($record->payload, true, 512, JSON_THROW_ON_ERROR);
        foreach (['' => 'product', 'target_' => 'target_product'] as $prefix => $field) {
            if (isset($this->form[$prefix.'dmdcomb'])) {
                $this->form[$field] = $this->form[$prefix.'dmdcomb'].'|'.$this->form[$prefix.'dmdctr'];
            }
        }
        $this->source = $record->source;
        $this->reviewedOn = substr($record->reviewed_on, 0, 10);
        $this->notice = 'Saving creates an immutable draft revision. Published information stays visible until the new revision is published.';
        $this->resetValidation();
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('edit-drug-clinical'), 403);
        $this->validate(['kind' => ['required', Rule::in(['mapping', 'side_effect', 'alternative', 'interaction'])]]);
        $data = $this->form;
        foreach (['product' => '', 'target_product' => 'target_'] as $field => $prefix) {
            if (! empty($data[$field])) {
                [$data[$prefix.'dmdcomb'], $data[$prefix.'dmdctr']] = array_pad(explode('|', $data[$field], 2), 2, null);
            } elseif (array_key_exists($field, $data)) {
                unset($data[$prefix.'dmdcomb'], $data[$prefix.'dmdctr']);
            }
        }
        if ($this->kind === 'side_effect') {
            $data['serious'] = (bool) ($data['serious'] ?? false);
        }
        app(DrugClinicalService::class)->saveDraft($this->kind, $data, $this->source, $this->reviewedOn, (string) auth()->id(), $this->editingId);
        $this->resetEditor();
        $this->notice = 'Draft saved. Publish after clinical review to make it visible in dispensing.';
    }

    public function transition(int $id, string $action): void
    {
        abort_unless(auth()->user()?->can('publish-drug-clinical'), 403);
        app(DrugClinicalService::class)->transition($id, $action, (string) auth()->id());
        $this->notice = $action === 'publish' ? 'Clinical revision published.' : 'Clinical revision retired.';
    }

    public function render()
    {
        $this->authorizeView();
        $db = DB::connection('hospital');
        $available = $db->getSchemaBuilder()->hasTable('pharm_clinical_records');
        $records = $available ? $db->table('pharm_clinical_records')->where('kind', $this->kind)
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('payload', 'like', '%'.$this->search.'%')->orWhere('source', 'like', '%'.$this->search.'%')))
            ->orderByDesc('id')->paginate(15) : null;
        $audits = $records ? $db->table('pharm_clinical_audits')->whereIn('record_id', $records->pluck('id'))->orderBy('id')->get()->groupBy('record_id') : collect();
        $ingredients = $available ? $db->table('pharm_clinical_ingredients')->orderBy('name')->get() : collect();
        $products = $db->table('hdmhdr')->where('dmdstat', 'A')->when($this->productSearch,
            fn ($q) => $q->where(fn ($q) => $q->where('drug_concat', 'like', '%'.$this->productSearch.'%')->orWhere('dmdcomb', 'like', '%'.$this->productSearch.'%')))->orderBy('dmdcomb')->limit(60)->get();
        $chosen = array_filter([$this->product, $this->form['product'] ?? '', $this->form['target_product'] ?? '']);
        foreach ($chosen as $key) {
            [$comb, $ctr] = array_pad(explode('|', $key, 2), 2, '');
            $row = $db->table('hdmhdr')->where('dmdcomb', $comb)->where('dmdctr', $ctr)->first();
            if ($row) {
                $products->push($row);
            }
        }
        $products = $products->unique(fn ($r) => $r->dmdcomb.'|'.$r->dmdctr);

        return view('livewire.pharmacy.manage-drug-clinical', compact('available', 'records', 'ingredients', 'products', 'audits'));
    }
}
