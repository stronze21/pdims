<?php

namespace App\Livewire\Pharmacy\Purchases;

use App\Helpers\DateHelper;
use App\Models\Pharmacy\DeliveryDetail;
use App\Models\Pharmacy\DeliveryItems;
use App\Models\Pharmacy\Drug;
use App\Models\Pharmacy\DrugPrice;
use App\Models\Pharmacy\PharmLocation;
use App\Models\References\ChargeCode;
use App\Models\References\Supplier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;
use Carbon\Carbon;

class ShowRis extends Component
{
    use Toast;

    public $risId;
    public $drugSearchTerm = '';
    public $selectedItemId = null;
    public $searchResults = [];
    public $loading = true;

    // Essential public properties for state
    public $risNo = null;
    public $risDate = null;
    public $officeName = null;
    public $rcc = null;
    public $purpose = null;
    public $dataLoaded = false;

    // Drug association status
    public $associationStatus = [
        'total' => 0,
        'associated' => 0,
        'percentage' => 0,
        'allAssociated' => false
    ];

    // Protected properties
    protected $ris = null;
    protected $risDetails = null;
    protected $relatedIar = null;

    // Transfer modal
    public $isTransferModalOpen = false;
    public $isDrugModalOpen = false;
    public $deliveryData = [
        'suppcode' => '',
        'delivery_type' => 'RIS',
        'charge_code' => '',
        'pharm_location_id' => '',
        'delivery_date' => '',
        'si_no' => '',
        'po_no' => ''
    ];

    public $relatedDeliveries = [];

    protected function rules()
    {
        return [
            'deliveryData.suppcode' => 'required',
            'deliveryData.delivery_type' => 'required',
            'deliveryData.charge_code' => 'required',
            'deliveryData.pharm_location_id' => 'required',
            'deliveryData.delivery_date' => 'required|date',
        ];
    }

    public function mount($id)
    {
        $this->risId = $id;
        $this->loadRis();
    }

    public function hydrate()
    {
        if ($this->dataLoaded && ($this->ris === null || $this->risDetails === null)) {
            $this->loadRisData();
        }
    }

    public function loadRis()
    {
        $this->loading = true;
        $this->dataLoaded = false;

        try {
            $this->loadRisData();
            $this->loadRelatedDeliveries();
            $this->dataLoaded = true;
            $this->calculateAssociationStatus();
        } catch (\Exception $e) {
            $this->error('Error loading RIS data: ' . $e->getMessage());
        } finally {
            $this->loading = false;
        }
    }

    protected function loadRisData()
    {
        $this->ris = DB::connection('pims')
            ->table('tbl_ris')
            ->from(DB::raw('tbl_ris'))
            ->select([
                'tbl_ris.risid',
                'tbl_ris.risno',
                'tbl_ris.purpose',
                DB::raw("DATE_FORMAT(tbl_ris.risdate, '%b-%d-%Y') AS formatted_risdate"),
                'tbl_ris.officeID',
                DB::raw("DATE_FORMAT(tbl_ris.requestdate, '%b-%d-%Y') AS formatted_requestdate"),
                'tbl_ris.apprvdby',
                'tbl_ris.apprvdby_desig',
                DB::raw("DATE_FORMAT(tbl_ris.apprvddate, '%b-%d-%Y') AS formatted_approveddate"),
                DB::raw("DATE_FORMAT(tbl_ris.issueddate, '%b-%d-%Y') AS formatted_issueddate"),
                'tbl_ris.receivedby',
                'tbl_ris.receivedby_desig',
                DB::raw("DATE_FORMAT(tbl_ris.receiveddate, '%b-%d-%Y') AS formatted_receiveddate"),
                'tbl_ris.apprvstat',
                'tbl_ris.issuedstat',
                'tbl_ris.status',
                'tbl_ris.ris_in_iar',
                'tbl_ris.iarid',
                'tbl_ris.transferred_to_pdims',
                'tbl_ris.transferred_at',
                'tbl_office.officeName',
                'tbl_office.rcc',
                'req.fullName AS requested_by_name',
                'req.designation AS requested_by_desig',
                'issue.fullName AS issued_by_name',
                'issue.designation AS issued_by_desig',
                'po.poNo',
                'tbl_iar_details.invoiceno'
            ])
            ->leftJoin('tbl_iar AS iar', 'iar.iarID', '=', 'tbl_ris.iarid')
            ->join(DB::raw('tbl_iar_details'), 'iar.iarID', '=', 'tbl_iar_details.iarID')
            ->join(DB::raw('tbl_items'), 'tbl_items.itemid', '=', 'tbl_iar_details.itemid')
            ->leftJoin(DB::raw('tbl_user AS req'), 'req.userID', '=', 'tbl_ris.requestby')
            ->leftJoin('tbl_po AS po', 'po.poID', '=', 'iar.poid')
            ->leftJoin(DB::raw('tbl_user AS issue'), 'issue.userID', '=', 'tbl_ris.issuedby')
            ->join(DB::raw('tbl_office'), 'tbl_office.officeID', '=', 'tbl_ris.officeID')
            ->where('tbl_ris.risid', $this->risId)
            ->where('tbl_items.catid', 9)
            ->first();

        if (!$this->ris) {
            $this->error('RIS not found');
            return redirect()->route('purchases.ris');
        }

        $this->risNo = $this->ris->risno;
        $this->risDate = $this->ris->formatted_risdate;
        $this->officeName = $this->ris->officeName;
        $this->rcc = $this->ris->rcc;
        $this->purpose = $this->ris->purpose;

        $this->risDetails = DB::connection('pims')
            ->table('tbl_ris_details')
            ->from(DB::raw('tbl_ris_details'))
            ->select([
                'tbl_ris_details.risdetid',
                'tbl_ris_details.stockno',
                'tbl_ris_details.onhand',
                'tbl_ris_details.itmqty',
                'tbl_items.itemID',
                'tbl_items.description',
                'tbl_items.unit',
                'tbl_items.pdims_itemcode',
                'tbl_items.pdims_drugdesc'
            ])
            ->join(DB::raw('tbl_items'), 'tbl_items.itemID', '=', 'tbl_ris_details.itemID')
            ->where('tbl_ris_details.risid', $this->risId)
            ->where('tbl_ris_details.status', 'A')
            ->get();

        foreach ($this->risDetails as $detail) {
            $detail->fundSources = DB::connection('pims')
                ->table('tbl_ris_release')
                ->from(DB::raw('tbl_ris_release'))
                ->select([
                    'tbl_ris_release.slcID',
                    'tbl_ris_release.releaseqty',
                    'tbl_ris_release.fsid',
                    'tbl_ris_release.unitprice',
                    'tbl_ris_release.risreleaseid',
                    'tbl_fund_source.fsname'
                ])
                ->leftJoin(DB::raw('tbl_fund_source'), 'tbl_fund_source.fsid', '=', 'tbl_ris_release.fsid')
                ->where('tbl_ris_release.risdetid', $detail->risdetid)
                ->where('tbl_ris_release.status', 'A')
                ->get();

            if (count($detail->fundSources) > 0) {
                $releaseIds = $detail->fundSources->pluck('risreleaseid')->toArray();

                $batchAndExpiryInfo = DB::connection('pims')
                    ->table('tbl_supply_slc')
                    ->from(DB::raw('tbl_supply_slc'))
                    ->select([
                        'tbl_supply_slc.lotno',
                        'tbl_supply_slc.expiredate',
                        'tbl_supply_slc.pono',
                        'tbl_iar_details.batch_no',
                        'tbl_iar_details.invoiceno',
                        'tbl_iar_details.expire_date'
                    ])
                    ->leftJoin(DB::raw('tbl_iar_details'), 'tbl_iar_details.iardetailsid', '=', 'tbl_supply_slc.iardetid')
                    ->whereIn('tbl_supply_slc.risreleaseid', $releaseIds)
                    ->where('tbl_supply_slc.status', 'A')
                    ->first();

                if ($batchAndExpiryInfo) {
                    $detail->batch_no = $batchAndExpiryInfo->batch_no;
                    $detail->invoiceno = $batchAndExpiryInfo->invoiceno ?? null;
                    $parsedExpiry = DateHelper::parseExpiryDate($batchAndExpiryInfo->expire_date);
                    $detail->expire_date = $parsedExpiry['raw'];
                    $detail->formatted_expire_date = $parsedExpiry['formatted'];
                    $detail->sql_formatted_expire_date = $parsedExpiry['sql_format'];
                } else {
                    $detail->batch_no = null;
                    $detail->expire_date = null;
                    $detail->formatted_expire_date = 'N/A';
                    $detail->sql_formatted_expire_date = null;
                }
            } else {
                $detail->batch_no = null;
                $detail->expire_date = null;
                $detail->formatted_expire_date = 'N/A';
                $detail->sql_formatted_expire_date = null;
            }
        }

        if ($this->ris && $this->ris->iarid) {
            $this->relatedIar = DB::connection('pims')
                ->table('tbl_iar')
                ->from(DB::raw('tbl_iar'))
                ->select([
                    'tbl_iar.iarID',
                    'tbl_iar.iarNo',
                    'tbl_iar.invoiceNo',
                    DB::raw("DATE_FORMAT(tbl_iar.iardate, '%b-%d-%Y') AS formatted_iardate"),
                    'tbl_iar.supplier'
                ])
                ->where('tbl_iar.iarID', $this->ris->iarid)
                ->first();
        }

        $this->calculateAssociationStatus();
    }

    protected function loadRelatedDeliveries()
    {
        if (!$this->ris || !$this->ris->transferred_to_pdims) {
            $this->relatedDeliveries = [];
            return;
        }

        try {
            $deliveries = DeliveryDetail::with(['supplier'])
                ->where('po_no', $this->ris->poNo)
                ->get();

            $deliveries = $deliveries->map(function ($delivery) {
                $delivery->items_count = DeliveryItems::where('delivery_id', $delivery->id)->count();
                $delivery->total_amount = DeliveryItems::where('delivery_id', $delivery->id)
                    ->selectRaw('SUM(qty * unit_price) as total')
                    ->first()->total ?? 0;
                return $delivery;
            });

            $grouped = $deliveries->groupBy(function ($delivery) {
                return $delivery->si_no ?: 'NO_INVOICE';
            });

            $this->relatedDeliveries = $grouped->map(function ($deliveriesInGroup) {
                return $deliveriesInGroup->map(function ($delivery) {
                    return [
                        'id' => $delivery->id,
                        'po_no' => $delivery->po_no,
                        'si_no' => $delivery->si_no,
                        'delivery_date' => $delivery->delivery_date,
                        'delivery_type' => $delivery->delivery_type,
                        'items_count' => $delivery->items_count,
                        'total_amount' => $delivery->total_amount,
                        'supplier_name' => $delivery->supplier->suppname ?? 'N/A',
                        'status' => $delivery->status ?? 'pending'
                    ];
                })->toArray();
            })->toArray();
        } catch (\Exception $e) {
            $this->relatedDeliveries = [];
        }
    }

    protected function calculateAssociationStatus()
    {
        if (!$this->risDetails) {
            $this->associationStatus = [
                'total' => 0,
                'associated' => 0,
                'percentage' => 0,
                'allAssociated' => false
            ];
            return;
        }

        $total = count($this->risDetails);
        $associated = 0;

        foreach ($this->risDetails as $detail) {
            if (!empty($detail->pdims_itemcode)) {
                $associated++;
            }
        }

        $percentage = $total > 0 ? round(($associated / $total) * 100) : 0;

        $this->associationStatus = [
            'total' => $total,
            'associated' => $associated,
            'percentage' => $percentage,
            'allAssociated' => ($total > 0 && $associated === $total)
        ];
    }

    public function openDrugModal($itemId)
    {
        $this->selectedItemId = $itemId;

        $selectedItem = collect($this->risDetails)->firstWhere('itemID', $itemId);

        if ($selectedItem && isset($selectedItem->description)) {
            $firstWord = explode(' ', trim($selectedItem->description))[0];
            $this->drugSearchTerm = preg_replace('/[^A-Za-z0-9 ]/', '', $firstWord);
            $this->searchDrugs();
        } else {
            $this->drugSearchTerm = '';
            $this->searchResults = [];
        }

        $this->isDrugModalOpen = true;
    }

    public function searchDrugs()
    {
        if (strlen($this->drugSearchTerm) >= 2) {
            try {
                $drugs = Drug::query()
                    ->has('generic')
                    ->where(function ($query) {
                        $query->whereRaw("brandname LIKE ?", ['%' . $this->drugSearchTerm . '%'])
                            ->orWhereRaw("drug_concat LIKE ?", ['%' . $this->drugSearchTerm . '%']);
                    })
                    ->limit(30)
                    ->get(['dmdcomb', 'dmdctr', 'brandname', 'drug_concat']);

                $this->searchResults = $drugs->map(function ($drug) {
                    return [
                        'id' => $drug->dmdcomb . '.' . $drug->dmdctr,
                        'name' => str_replace('_', ' ', $drug->drug_concat),
                        'dmdcomb' => $drug->dmdcomb,
                        'dmdctr' => $drug->dmdctr
                    ];
                })->toArray();
            } catch (\Exception $e) {
                $this->searchResults = [];
            }
        } else {
            $this->searchResults = [];
        }
    }

    public function associateDrug($drugData)
    {
        try {
            $pdims_itemcode = $drugData;
            $pdims_drugdesc = $drugData;

            if (strpos($drugData, '.') !== false) {
                list($dmdcomb, $dmdctr) = explode('.', $drugData);
                $drug = Drug::where('dmdcomb', $dmdcomb)
                    ->where('dmdctr', $dmdctr)
                    ->first();
                if ($drug) {
                    $pdims_drugdesc = str_replace('_', ' ', $drug->drug_concat);
                }
            }

            DB::connection('pims')
                ->table('tbl_items')
                ->where('itemID', $this->selectedItemId)
                ->update([
                    'pdims_itemcode' => $pdims_itemcode,
                    'pdims_drugdesc' => $pdims_drugdesc
                ]);

            foreach ($this->risDetails as $key => $detail) {
                if ($detail->itemID == $this->selectedItemId) {
                    $this->risDetails[$key]->pdims_itemcode = $pdims_itemcode;
                    $this->risDetails[$key]->pdims_drugdesc = $pdims_drugdesc;
                    break;
                }
            }

            $this->calculateAssociationStatus();
            $this->isDrugModalOpen = false;
            $this->drugSearchTerm = '';
            $this->searchResults = [];
            $this->success('Drug successfully associated with item.');
        } catch (\Exception $e) {
            $this->error('Error associating drug: ' . $e->getMessage());
        }
    }

    public function removeDrugAssociation($itemId)
    {
        try {
            DB::connection('pims')
                ->table('tbl_items')
                ->where('itemID', $itemId)
                ->update([
                    'pdims_itemcode' => null,
                    'pdims_drugdesc' => null
                ]);

            $this->loadRis();
            $this->success('Drug association removed.');
        } catch (\Exception $e) {
            $this->error('Error removing drug association: ' . $e->getMessage());
        }
    }

    public function openTransferModal()
    {
        if ($this->ris && $this->ris->transferred_to_pdims) {
            $this->error('This RIS has already been transferred.');
            return;
        }

        if (!$this->associationStatus['allAssociated']) {
            $this->error('All items must be linked to drugs before transfer.');
            return;
        }

        $this->resetTransferForm();
        $this->deliveryData['po_no'] = $this->ris->poNo ?? $this->risNo;
        $this->deliveryData['delivery_date'] = date('Y-m-d');
        $this->deliveryData['delivery_type'] = 'RIS';

        $pharmacyLocations = PharmLocation::whereNull('deleted_at')->get();
        if ($pharmacyLocations->isNotEmpty()) {
            $this->deliveryData['pharm_location_id'] = $pharmacyLocations->first()->id;
        }

        $this->isTransferModalOpen = true;
    }

    public function closeTransferModal()
    {
        $this->isTransferModalOpen = false;
        $this->resetTransferForm();
    }

    public function resetTransferForm()
    {
        $this->deliveryData = [
            'suppcode' => '',
            'delivery_type' => 'RIS',
            'charge_code' => '',
            'pharm_location_id' => '',
            'delivery_date' => '',
            'si_no' => '',
            'po_no' => ''
        ];
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function transferToDelivery()
    {
        $this->validate();

        try {
            DB::beginTransaction();

            $itemsByInvoice = collect($this->risDetails)->groupBy(function ($detail) {
                return $detail->invoiceno ?? 'NO_INVOICE';
            });

            $createdDeliveries = [];
            $transferredItemsCount = 0;

            foreach ($itemsByInvoice as $invoiceNo => $invoiceItems) {
                $validItems = $invoiceItems->filter(function ($detail) {
                    return !empty($detail->pdims_itemcode);
                });

                if ($validItems->isEmpty()) {
                    continue;
                }

                $delivery = new DeliveryDetail();
                $delivery->po_no = $this->deliveryData['po_no'] ?? $this->risNo;
                $delivery->si_no = ($invoiceNo !== 'NO_INVOICE') ? $invoiceNo : ($this->deliveryData['si_no'] ?? '');
                $delivery->pharm_location_id = $this->deliveryData['pharm_location_id'];
                $delivery->user_id = Auth::id();
                $delivery->delivery_date = $this->deliveryData['delivery_date'];
                $delivery->suppcode = $this->deliveryData['suppcode'];
                $delivery->delivery_type = $this->deliveryData['delivery_type'];
                $delivery->charge_code = $this->deliveryData['charge_code'];
                $delivery->save();

                $createdDeliveries[] = $delivery;

                foreach ($validItems as $detail) {
                    list($dmdcomb, $dmdctr) = explode('.', $detail->pdims_itemcode);

                    $unit_cost = 0;
                    $total_amount = 0;

                    if (isset($detail->fundSources) && count($detail->fundSources) > 0) {
                        $unit_cost = $detail->fundSources[0]->unitprice ?? 0;
                        $total_amount = $detail->itmqty * $unit_cost;
                    }

                    $markup_price = $this->calculateMarkup($unit_cost);
                    $retail_price = $unit_cost + $markup_price;

                    if (in_array($delivery->charge_code, ['DRUMAN', 'DRUMAA'])) {
                        $retail_price = 0;
                    }

                    $deliveryItem = new DeliveryItems();
                    $deliveryItem->delivery_id = $delivery->id;
                    $deliveryItem->dmdcomb = $dmdcomb;
                    $deliveryItem->dmdctr = $dmdctr;
                    $deliveryItem->qty = $detail->itmqty;
                    $deliveryItem->unit_price = $unit_cost;
                    $deliveryItem->total_amount = $total_amount;
                    $deliveryItem->retail_price = $retail_price;
                    $deliveryItem->lot_no = $detail->batch_no ?? '';
                    $deliveryItem->expiry_date = $detail->sql_formatted_expire_date ?? date('Y-m-d', strtotime('+1 year'));
                    $deliveryItem->pharm_location_id = $this->deliveryData['pharm_location_id'];
                    $deliveryItem->charge_code = $this->deliveryData['charge_code'];
                    $deliveryItem->save();

                    $attributes = [
                        'dmdcomb' => $deliveryItem->dmdcomb,
                        'dmdctr' => $deliveryItem->dmdctr,
                        'dmhdrsub' => $delivery->charge_code,
                        'dmduprice' => $unit_cost,
                        'dmselprice' => $deliveryItem->retail_price,
                        'expdate' => $deliveryItem->expiry_date,
                        'stock_id' => $deliveryItem->id,
                        'mark_up' => $markup_price,
                        'acquisition_cost' => $unit_cost,
                        'has_compounding' => false,
                        'retail_price' => $retail_price
                    ];

                    $new_price = DrugPrice::firstOrCreate($attributes, ['dmdprdte' => now()]);
                    $deliveryItem->dmdprdte = $new_price->dmdprdte;
                    $deliveryItem->save();

                    DB::connection('pims')
                        ->table('tbl_ris_details')
                        ->where('risdetid', $detail->risdetid)
                        ->update([
                            'transferred_to_pdims' => $delivery->id,
                            'transferred_at' => now()
                        ]);

                    $transferredItemsCount++;
                }
            }

            $primaryDeliveryId = !empty($createdDeliveries) ? $createdDeliveries[0]->id : null;

            if ($primaryDeliveryId) {
                DB::connection('pims')
                    ->table('tbl_ris')
                    ->where('risid', $this->risId)
                    ->update([
                        'transferred_to_pdims' => $primaryDeliveryId,
                        'transferred_at' => now()
                    ]);
            }

            DB::commit();

            $this->closeTransferModal();
            $deliveryCount = count($createdDeliveries);

            if ($deliveryCount === 1) {
                return redirect()->route('purchases.delivery-view', [$createdDeliveries[0]->id]);
            } else {
                session()->flash('info', "Multiple deliveries were created. Viewing the first one.");
                return redirect()->route('purchases.delivery-view', [$createdDeliveries[0]->id]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error transferring items: ' . $e->getMessage());
        }
    }

    private function calculateMarkup($unit_cost)
    {
        if ($unit_cost >= 10000.01) {
            return 1115 + (($unit_cost - 10000) * 0.05);
        } elseif ($unit_cost >= 1000.01) {
            return 215 + (($unit_cost - 1000) * 0.10);
        } elseif ($unit_cost >= 100.01) {
            return 35 + (($unit_cost - 100) * 0.20);
        } elseif ($unit_cost >= 50.01) {
            return 20 + (($unit_cost - 50) * 0.30);
        } elseif ($unit_cost >= 0.01) {
            return $unit_cost * 0.40;
        }
        return 0;
    }

    public function render()
    {
        $suppliers = Supplier::orderBy('suppname')->get();

        $chargeCodes = ChargeCode::where('bentypcod', 'DRUME')
            ->where('chrgstat', 'A')
            ->whereIn('chrgcode', app('chargetable'))
            ->get();

        $pharmacyLocations = PharmLocation::whereNull('deleted_at')->get();

        return view('livewire.pharmacy.purchases.show-ris', [
            'ris' => $this->ris,
            'risDetails' => $this->risDetails,
            'relatedIar' => $this->relatedIar,
            'loading' => $this->loading,
            'suppliers' => $suppliers,
            'chargeCodes' => $chargeCodes,
            'pharmacyLocations' => $pharmacyLocations,
        ])->layout('layouts.app', ['title' => 'RIS # ' . ($this->risNo ?? $this->risId)]);
    }
}
