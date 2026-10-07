<?php

namespace App\Livewire\Pharmacy\Drugs;

use App\Models\Pharmacy\DrugPrice;
use App\Models\Pharmacy\Drugs\DrugStock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class MissingDrugPrices extends Component
{
    use Toast;
    use WithPagination;

    public $search = '';
    public $location_id = '';
    public $selected_stock_id;
    public $selected_drug = '';
    public $acquisition_cost;
    public $selling_price;
    public $repairModal = false;

    public function mount()
    {
        $this->authorizeSuperAdmin();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingLocationId()
    {
        $this->resetPage();
    }

    public function selectForManualRepair($stockId)
    {
        $this->authorizeSuperAdmin();

        $stock = $this->orphanQuery()->where('pharm_drug_stocks.id', $stockId)->firstOrFail();

        $this->selected_stock_id = $stock->id;
        $this->selected_drug = $stock->drug_concat;
        $this->acquisition_cost = null;
        $this->selling_price = $stock->retail_price;
        $this->repairModal = true;
        $this->resetValidation();
    }

    public function cancelManualRepair()
    {
        $this->resetRepairForm();
    }

    public function attachLatestMatchingPrice($stockId)
    {
        $this->authorizeSuperAdmin();

        DB::connection('hospital')->transaction(function () use ($stockId) {
            $stock = DrugStock::lockForUpdate()->findOrFail($stockId);

            abort_unless($this->isOrphan($stock), 409, 'This stock already has a valid price record.');

            $price = $this->matchingPrices($stock)->first();
            abort_unless($price, 422, 'No matching price record is available.');

            $stock->dmdprdte = $price->dmdprdte;
            $stock->retail_price = $price->retail_price ?? $price->dmselprice;
            $stock->save();

            Log::notice('Super Admin repaired an orphan pharmacy stock using an existing price.', [
                'user_id' => Auth::id(),
                'stock_id' => $stock->id,
                'dmdprdte' => $price->dmdprdte,
            ]);
        });

        $this->success('The latest matching price was attached.');
    }

    public function createPriceAndRepair()
    {
        $this->authorizeSuperAdmin();

        $validated = $this->validate([
            'selected_stock_id' => ['required', 'integer'],
            'acquisition_cost' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::connection('hospital')->transaction(function () use ($validated) {
            $stock = DrugStock::lockForUpdate()->findOrFail($validated['selected_stock_id']);

            abort_unless($this->isOrphan($stock), 409, 'This stock already has a valid price record.');

            $dmdprdte = now();
            $acquisitionCost = (float) $validated['acquisition_cost'];
            $sellingPrice = (float) $validated['selling_price'];

            DrugPrice::create([
                'dmdcomb' => $stock->dmdcomb,
                'dmdctr' => $stock->dmdctr,
                'dmhdrsub' => $stock->chrgcode,
                'dmduprice' => $acquisitionCost,
                'dmselprice' => $sellingPrice,
                'dmdprdte' => $dmdprdte,
                'expdate' => $stock->exp_date,
                'stock_id' => $stock->id,
                'mark_up' => max(0, $sellingPrice - $acquisitionCost),
                'acquisition_cost' => $acquisitionCost,
                'has_compounding' => false,
                'retail_price' => $sellingPrice,
            ]);

            $stock->dmdprdte = $dmdprdte;
            $stock->retail_price = $sellingPrice;
            $stock->save();

            Log::notice('Super Admin created a price to repair an orphan pharmacy stock.', [
                'user_id' => Auth::id(),
                'stock_id' => $stock->id,
                'dmdprdte' => $dmdprdte->format('Y-m-d H:i:s'),
            ]);
        });

        $this->resetRepairForm();
        $this->success('A price record was created and attached to the stock.');
    }

    public function render()
    {
        $this->authorizeSuperAdmin();

        $stocks = $this->orphanQuery()
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('pharm_drug_stocks.drug_concat', 'like', '%' . $this->search . '%')
                        ->orWhere('pharm_drug_stocks.lot_no', 'like', '%' . $this->search . '%')
                        ->orWhere('pharm_drug_stocks.chrgcode', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->location_id, fn ($query) => $query->where('pharm_drug_stocks.loc_code', $this->location_id))
            ->orderBy('pharm_drug_stocks.drug_concat')
            ->paginate(20);

        $matchingPriceIds = [];
        foreach ($stocks as $stock) {
            $matchingPriceIds[$stock->id] = optional($this->matchingPrices($stock)->first())->dmdprdte;
        }

        return view('livewire.pharmacy.drugs.missing-drug-prices', [
            'stocks' => $stocks,
            'locations' => DB::connection('hospital')->table('pharm_locations')->orderBy('description')->get(),
            'matchingPriceIds' => $matchingPriceIds,
        ]);
    }

    private function orphanQuery()
    {
        return DrugStock::query()
            ->leftJoin('hdmhdrprice as current_price', 'current_price.dmdprdte', '=', 'pharm_drug_stocks.dmdprdte')
            ->leftJoin('hcharge', 'hcharge.chrgcode', '=', 'pharm_drug_stocks.chrgcode')
            ->leftJoin('pharm_locations', 'pharm_locations.id', '=', 'pharm_drug_stocks.loc_code')
            ->whereNull('current_price.dmdprdte')
            ->select(['pharm_drug_stocks.*', 'hcharge.chrgdesc', 'pharm_locations.description as location_name']);
    }

    private function matchingPrices(DrugStock $stock)
    {
        return DrugPrice::query()
            ->where('dmdcomb', $stock->dmdcomb)
            ->where('dmdctr', $stock->dmdctr)
            ->where('dmhdrsub', $stock->chrgcode)
            ->whereDate('expdate', $stock->exp_date)
            ->orderByDesc('dmdprdte');
    }

    private function isOrphan(DrugStock $stock)
    {
        return !$stock->dmdprdte || !DrugPrice::where('dmdprdte', $stock->dmdprdte)->exists();
    }

    private function resetRepairForm()
    {
        $this->reset('selected_stock_id', 'selected_drug', 'acquisition_cost', 'selling_price', 'repairModal');
        $this->resetValidation();
    }

    private function authorizeSuperAdmin()
    {
        abort_unless(Auth::check() && Auth::user()->hasRole('Super Admin'), 403);
    }
}
