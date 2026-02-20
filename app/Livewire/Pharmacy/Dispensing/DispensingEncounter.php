<?php

namespace App\Livewire\Pharmacy\Dispensing;

use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

use App\Models\Pharmacy\Drug;
use App\Models\Pharmacy\Dispensing\DrugOrder;
use App\Models\Pharmacy\Dispensing\DrugOrderReturn;
use App\Models\Pharmacy\Dispensing\OrderChargeCode;
use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\Pharmacy\Drugs\DrugStockCard;
use App\Models\Pharmacy\Drugs\DrugStockIssue;
use App\Models\Pharmacy\Drugs\DrugStockLog;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;
use App\Models\Record\Encounters\EncounterLog;
use App\Models\Record\Patients\Patient;
use App\Models\Record\Prescriptions\Prescription;
use App\Models\Record\Prescriptions\PrescriptionData;
use App\Models\Record\Prescriptions\PrescriptionDataIssued;
use App\Models\Hospital\Ward;
use App\Models\References\ChargeCode;
use App\Services\Pharmacy\PrescriptionQueueService;

class DispensingEncounter extends Component
{
    use Toast;

    public function getListeners()
    {
        $locationCode = auth()->user()->pharm_location_id;

        return [
            "echo:pharmacy.location.{$locationCode},.queue.status.changed" => 'handleQueueStatusChanged',
            "echo:pharmacy.location.{$locationCode},.queue.called" => 'handleQueueCalled',
        ];
    }

    public function handleQueueStatusChanged($event)
    {
        if ($this->queueId && $event['queue_id'] == $this->queueId) {
            $this->refreshQueueStatus();
        }
        $this->loadQueueList();
    }

    public function handleQueueCalled($event)
    {
        $this->loadQueueList();
    }

    // Queue Integration
    public $queueId = null;
    public $currentQueueNumber = null;
    public $currentQueueStatus = null;
    public $queueChargeSlipNo = null;
    public $showQueuePanel = false;
    public $queueList = [];
    public $chargedQueues = [];
    public $chargedEncounters = [];

    // Patient / Encounter
    public $enccode, $code, $encdate, $hpercode, $toecode, $mssikey;
    public $patlast, $patfirst, $patmiddle;
    public $diagtext, $wardname, $rmname, $billstat;
    public $location_id;
    public $hasEncounter = false;
    protected $encounter = [];

    // Drug Search & Filter
    public $generic = '';
    public $charge_code_filter = [];
    public $charges;
    public $stocksDisplayCount = 50;

    // Order Management
    public $selected_items = [];
    public $order_qty, $unit_price, $return_qty, $docointkey;
    public $remarks;
    public $tag;

    // Issue Type Flags
    public $ems = false, $maip = false, $wholesale = false, $caf = false;
    public $konsulta = false, $pcso = false, $phic = false;
    public $pay = false, $service = false, $doh_free = false;
    public $is_ris = false, $bnb = false;
    public $type, $deptcode;

    // Stock Item Selection
    public $item_id, $item_chrgcode, $item_dmdcomb, $item_dmdctr;
    public $item_loc_code, $item_dmdprdte, $item_exp_date, $item_stock_bal;

    // Prescriptions
    public $active_prescription = [], $extra_prescriptions = [];
    public $active_prescription_all = [], $extra_prescriptions_all = [];
    public $rx_id, $rx_dmdcomb, $rx_dmdctr, $empid, $rx_charge_code;
    public $rx_available_charges = [];

    // Remarks Edit
    public $selected_remarks, $new_remarks;

    // Deactivate Rx
    public $adttl_remarks;

    // Print Prescriptions
    public $showPrintModal = false;
    public $printItems = [];
    public $printSelectedItems = [];

    // Modal States
    public $showAddItemModal = false;
    public $showPrescribedItemModal = false;
    public $showUpdateQtyModal = false;
    public $showReturnModal = false;
    public $showDeactivateRxModal = false;
    public $showSummaryModal = false;
    public $showIssueModal = false;
    public $showPrescriptionListModal = false;
    public $showEncounterSelectorModal = false;

    // Encounter / Prescription Selector
    public $patient_encounters = [];
    public $selected_encounter_code = null;
    public $selected_encounter_prescriptions = [];
    public $selected_encounter_orders = [];
    public $encounter_area_filter = 'all';
    public $encounter_detail_tab = 'prescriptions';

    // Patient Search (within encounter selector)
    public $selector_search_hpercode = '';
    public $selector_search_lastname = '';
    public $selector_search_firstname = '';
    public $selector_patient_results = [];
    public $selector_selected_hpercode = null;
    public $selector_patient_name = null;

    // Rx/Orders Browsing (area-based)
    public $selector_mode = 'patient'; // 'patient' or 'rx_orders'
    public $rx_browse_area = 'opd'; // 'opd', 'ward', 'er'
    public $rx_browse_date;
    public $rx_browse_search = '';
    public $rx_browse_tag_filter = 'all'; // 'all', 'basic', 'g24', 'or'
    public $rx_browse_results = [];
    public $rx_browse_wardcode = '';

    public function mount($enccode = null)
    {
        $this->location_id = auth()->user()->pharm_location_id;
        $this->rx_browse_date = date('Y-m-d');

        if (!$this->charges) {
            $this->charges = ChargeCode::where('bentypcod', 'DRUME')
                ->where('chrgstat', 'A')
                ->whereIn('chrgcode', app('chargetable'))
                ->get();
        }

        // Load queue context if passed via query string
        $requestQueueId = request()->query('queue_id');
        if ($requestQueueId) {
            $this->loadQueueContext($requestQueueId);
        }

        // Always load queue list for the queue controller bar
        $this->loadQueueList();

        if (!$enccode) {
            $this->hasEncounter = false;
            // Show queue panel by default in empty state
            $this->showQueuePanel = true;
            return;
        }

        $this->enccode = $enccode;
        $this->hasEncounter = true;

        $decrypted = $this->decryptEnccode();

        // Auto-detect queue by enccode if no queue_id was passed
        if (!$this->queueId) {
            $this->autoDetectQueueByEnccode($decrypted);
        }

        $encounter = collect(DB::select("SELECT TOP 1 enctr.hpercode, enctr.toecode, enctr.enccode, enctr.encdate, diag.diagtext,
                                                pat.patlast, pat.patfirst, pat.patmiddle,
                                                mss.mssikey, ward.wardname, room.rmname, track.billstat
                                FROM henctr as enctr
                                LEFT JOIN hactrack as track ON enctr.enccode = track.enccode
                                LEFT JOIN hencdiag as diag ON enctr.enccode = diag.enccode
                                INNER JOIN hperson as pat ON enctr.hpercode = pat.hpercode
                                LEFT JOIN hpatmss as mss ON enctr.enccode = mss.enccode
                                LEFT JOIN hpatroom as patroom ON enctr.enccode = patroom.enccode
                                LEFT JOIN hward as ward ON patroom.wardcode = ward.wardcode
                                LEFT JOIN hroom as room ON patroom.rmintkey = room.rmintkey
                                WHERE enctr.enccode = '" . $decrypted . "'
                                ORDER BY patroom.hprdate DESC
                                "))->first();

        $this->loadPrescriptions($decrypted, $encounter);

        if (!$this->hpercode) {
            $this->hpercode = $encounter->hpercode;
            $this->toecode = $encounter->toecode;
        }

        $this->mssikey = $encounter->mssikey;
        $this->encounter = $encounter;
        $this->code = $encounter->enccode;
        $this->encdate = $encounter->encdate;
        $this->diagtext = $encounter->diagtext;
        $this->patlast = $encounter->patlast;
        $this->patfirst = $encounter->patfirst;
        $this->patmiddle = $encounter->patmiddle;
        $this->wardname = $encounter->wardname;
        $this->rmname = $encounter->rmname;
        $this->billstat = $encounter->billstat;
    }

    #[Layout('layouts.dispensing')]
    public function render()
    {
        if (!$this->hasEncounter) {
            return view('livewire.pharmacy.dispensing.dispensing-encounter', [
                'orders' => [],
                'stocks' => [],
                'departments' => [],
                'summaries' => [],
            ]);
        }

        $enccode = $this->decryptEnccode();

        $orders = $this->fetchOrders($enccode);

        $stocks = $this->fetchStocks();

        $summaries = DB::select("
            SELECT drug_concat, SUM(qtyissued) qty_issued, MAX(dodtepost) last_issue
                FROM hrxo
            JOIN hdmhdr ON hrxo.dmdcomb = hdmhdr.dmdcomb AND hrxo.dmdctr = hdmhdr.dmdctr
                WHERE enccode = '" . $enccode . "' AND estatus = 'S'
            GROUP BY drug_concat
        ");

        $departments = DB::select("SELECT * FROM hdept WHERE deptstat = 'A'");

        return view('livewire.pharmacy.dispensing.dispensing-encounter', compact(
            'orders',
            'stocks',
            'departments',
            'summaries',
        ));
    }

    public function selectStock($id, $chrgcode, $dmdcomb, $dmdctr, $loc_code, $dmdprdte, $exp_date, $stock_bal, $unit_price)
    {
        $this->item_id = $id;
        $this->item_chrgcode = $chrgcode;
        $this->item_dmdcomb = $dmdcomb;
        $this->item_dmdctr = $dmdctr;
        $this->item_loc_code = $loc_code;
        $this->item_dmdprdte = $dmdprdte;
        $this->item_exp_date = $exp_date;
        $this->item_stock_bal = $stock_bal;
        $this->unit_price = $unit_price;

        $this->showAddItemModal = true;
    }

    public function openUpdateQtyModal($docointkey, $qty, $unitPrice)
    {
        $this->docointkey = $docointkey;
        $this->order_qty = $qty;
        $this->unit_price = $unitPrice;
        $this->showUpdateQtyModal = true;
    }

    public function openReturnModal($docointkey, $unitPrice)
    {
        $this->docointkey = $docointkey;
        $this->unit_price = $unitPrice;
        $this->showReturnModal = true;
    }

    public function openRemarksModal($docointkey, $remarks)
    {
        $this->selected_remarks = $docointkey;
        $this->new_remarks = $remarks;
    }

    public function openPrescribedItemModal($rxId, $dmdcomb, $dmdctr, $empid, $qty)
    {
        $this->rx_id = $rxId;
        $this->rx_dmdcomb = $dmdcomb;
        $this->rx_dmdctr = $dmdctr;
        $this->empid = $empid;
        $this->order_qty = $qty;

        $this->loadAvailableCharges($dmdcomb, $dmdctr);
        $this->showPrescribedItemModal = true;
    }

    public function searchGenericItem($rxId, $generic, $dmdcomb, $dmdctr, $empid)
    {
        $this->rx_id = $rxId;
        $this->generic = $generic;
        $this->rx_dmdcomb = $dmdcomb;
        $this->rx_dmdctr = $dmdctr;
        $this->empid = $empid;
    }

    public function confirmDeactivateRx($rxId)
    {
        $this->rx_id = $rxId;
        $this->showDeactivateRxModal = true;
    }

    public function searchExtraGeneric($rxId, $generic, $dmdcomb, $dmdctr, $empid)
    {
        $this->rx_id = $rxId;
        $this->generic = $generic;
        $this->rx_dmdcomb = $dmdcomb;
        $this->rx_dmdctr = $dmdctr;
        $this->empid = $empid;
    }

    public function openPrescribedItemFromAll($rxId, $dmdcomb, $dmdctr, $empid, $qty)
    {
        $this->rx_id = $rxId;
        $this->rx_dmdcomb = $dmdcomb;
        $this->rx_dmdctr = $dmdctr;
        $this->empid = $empid;
        $this->order_qty = $qty;

        $this->loadAvailableCharges($dmdcomb, $dmdctr);
        $this->showPrescribedItemModal = true;
    }

    public function searchGenericFromAll($rxId, $generic, $dmdcomb, $dmdctr, $empid)
    {
        $this->rx_id = $rxId;
        $this->generic = $generic;
        $this->rx_dmdcomb = $dmdcomb;
        $this->rx_dmdctr = $dmdctr;
        $this->empid = $empid;
    }

    public function confirmDeactivatePrescription($rxId, $dmdcomb, $dmdctr, $empid)
    {
        $this->rx_id = $rxId;
        $this->rx_dmdcomb = $dmdcomb;
        $this->rx_dmdctr = $dmdctr;
        $this->empid = $empid;

        $this->showDeactivateRxModal = true;
    }

    public function selectIssueTag($selectedKey)
    {
        $tags = [
            'ems',
            'maip',
            'wholesale',
            'caf',
            'konsulta',
            'pcso',
            'phic',
            'is_ris',
            'pay'
        ];

        foreach ($tags as $tag) {
            $this->$tag = ($tag === $selectedKey);
        }
    }

    public function charge_items()
    {
        if (empty($this->selected_items)) {
            $this->warning('No items selected.');
            return;
        }

        $charge_code = OrderChargeCode::create(['charge_desc' => 'a']);
        $cnt = 0;
        $pcchrgcod = 'P' . date('y') . '-' . sprintf('%07d', $charge_code->id);

        foreach ($this->selected_items as $docointkey) {
            $cnt = DB::update(
                "UPDATE hospital.dbo.hrxo SET pcchrgcod = '" . $pcchrgcod . "', estatus = 'P' WHERE docointkey = " . $docointkey . " AND ((estatus = 'U' OR orderfrom = 'DRUMK' OR pchrgup = 0) AND pcchrgcod IS NULL)"
            );
        }

        if ($cnt && $cnt != 0) {
            // Auto-link charge slip number and update queue status to ready
            if ($this->queueId) {
                $queue = PrescriptionQueue::find($this->queueId);
                if ($queue && !$queue->isDispensed() && !$queue->isCancelled()) {
                    $queueService = app(PrescriptionQueueService::class);
                    $queueService->updateQueueStatus(
                        $this->queueId,
                        'ready',
                        auth()->user()->employeeid,
                        'Charge slip issued, ready for claiming: ' . $pcchrgcod
                    );

                    DB::connection('webapp')->table('prescription_queues')
                        ->where('id', $this->queueId)
                        ->update([
                            'charge_slip_no' => $pcchrgcod,
                            'charging_at' => now(),
                            'charged_by' => auth()->user()->employeeid,
                            'ready_at' => now(),
                        ]);
                    $this->queueChargeSlipNo = $pcchrgcod;
                    $this->currentQueueStatus = 'ready';
                }
            }

            $this->dispatch('open-charge-slip', pcchrgcod: $pcchrgcod);
        } else {
            $this->error('No item to charge.');
        }
    }

    public function issue_order()
    {
        $enccode = $this->decryptEnccode();
        $cnt = 0;

        if (empty($this->selected_items)) {
            $this->warning('No items selected.');
            return;
        }

        $selected_items = implode(',', $this->selected_items);
        $rxos = collect(DB::select("SELECT * FROM hrxo WHERE docointkey IN (" . $selected_items . ") AND (estatus = 'P' OR orderfrom = 'DRUMK' OR pchrgup = 0)"))->all();

        $this->type = $this->resolveTransactionType();

        if (!$this->isAdmittedEncounter() && $this->toecode != 'ER') {
            $this->validate(['deptcode' => 'required'], ['deptcode.required' => 'Please select department.']);
        }

        $tag = $this->type;

        foreach ($rxos as $rxo) {
            $stocks = $this->fetchStocksForIssue($rxo);

            if ($stocks) {
                $total_deduct = $rxo->pchrgqty;
                $docointkey = $rxo->docointkey;

                foreach ($stocks as $stock) {
                    $trans_qty = 0;
                    if ($total_deduct) {
                        if (!$rxo->ris) {
                            if ($total_deduct > $stock->stock_bal) {
                                $trans_qty = $stock->stock_bal;
                                $total_deduct -= $stock->stock_bal;
                                $stock_bal = 0;
                            } else {
                                $trans_qty = $total_deduct;
                                $stock_bal = $stock->stock_bal - $total_deduct;
                                $total_deduct = 0;
                            }
                            $cnt = DB::update(
                                "UPDATE hospital.dbo.pharm_drug_stocks SET stock_bal = '" . $stock_bal . "' WHERE id = '" . $stock->id . "'"
                            );
                        } else {
                            $total_deduct = 0;
                        }

                        $drug_concat = implode("", explode('_', $stock->drug_concat));

                        $this->logStockIssue(
                            $stock->id,
                            $docointkey,
                            $rxo->dmdcomb,
                            $rxo->dmdctr,
                            $rxo->loc_code,
                            $rxo->orderfrom,
                            $stock->exp_date,
                            $trans_qty,
                            $rxo->pchrgup,
                            $rxo->pcchrgamt,
                            auth()->id(),
                            $rxo->hpercode,
                            $rxo->enccode,
                            $this->toecode,
                            $rxo->pcchrgcod,
                            $tag,
                            $rxo->ris,
                            $stock->dmdprdte,
                            $stock->retail_price,
                            $drug_concat,
                            date('Y-m-d'),
                            now(),
                            null,
                            $stock->dmduprice
                        );
                    }
                }

                if ($cnt == 1) {
                    $cnt = DB::update(
                        "UPDATE hospital.dbo.hrxo SET estatus = 'S', qtyissued = '" . $rxo->pchrgqty . "', tx_type = '" . $tag . "', dodtepost = '" . now() . "', dotmepost = '" . now() . "', deptcode = '" . $this->deptcode . "' WHERE docointkey = '" . $rxo->docointkey . "' AND (estatus = 'P' OR orderfrom = 'DRUMK' OR pchrgup = 0)"
                    );
                    $this->logHrxoIssue(
                        $rxo->docointkey,
                        $rxo->enccode,
                        $rxo->hpercode,
                        $rxo->dmdcomb,
                        $rxo->dmdctr,
                        $rxo->pchrgqty,
                        auth()->user()->employeeid,
                        $rxo->orderfrom,
                        $rxo->pcchrgcod,
                        $rxo->pchrgup,
                        $rxo->ris,
                        $rxo->prescription_data_id,
                        now(),
                        $rxo->dmdprdte
                    );
                }
            } else {
                $insuf = Drug::select('drug_concat')->where('dmdcomb', $rxo->dmdcomb)->where('dmdctr', $rxo->dmdctr)->first();
                return $this->error('Insufficient Stock Balance. ' . $insuf->drug_concat);
            }
        }

        if ($cnt == 1) {
            $this->showIssueModal = false;
            $this->resetIssueFlags();

            // Auto-update queue status when items are issued
            $this->autoUpdateQueueOnIssue();

            $this->success('Order issued successfully.');
        } else {
            $this->error('No item to issue.');
        }
    }

    public function add_item()
    {
        $this->validate([
            'order_qty' => ['required', 'numeric', 'min:1'],
            'unit_price' => ['required', 'numeric'],
        ]);

        $dmdcomb = $this->item_dmdcomb;
        $dmdctr = $this->item_dmdctr;
        $chrgcode = $this->item_chrgcode;
        $loc_code = $this->item_loc_code;
        $dmdprdte = $this->item_dmdprdte;
        $id = $this->item_id;
        $available = $this->item_stock_bal ?? 0;
        $exp_date = $this->item_exp_date;

        $with_rx = ($dmdcomb == $this->rx_dmdcomb && $dmdctr == $this->rx_dmdctr);
        $rx_id = $with_rx ? $this->rx_id : null;
        $empid = $with_rx ? $this->empid : null;

        $this->type = $this->resolveTransactionType();

        if ($this->is_ris || $available >= $this->order_qty) {
            $enccode = $this->decryptEnccode();
            $docointkey = '0000040' . $this->hpercode . date('m/d/Yh:i:s', strtotime(now())) . $chrgcode . $dmdcomb . $dmdctr;

            DB::insert("INSERT INTO hospital.dbo.hrxo(docointkey, enccode, hpercode, rxooccid, rxoref, dmdcomb, repdayno1, rxostatus,
                            rxolock, rxoupsw, rxoconfd, dmdctr, estatus, entryby, ordcon, orderupd, locacode, orderfrom, issuetype,
                            has_tag, tx_type, ris, pchrgqty, pchrgup, pcchrgamt, dodate, dotime, dodtepost, dotmepost, dmdprdte, exp_date, loc_code, item_id, remarks, prescription_data_id, prescribed_by )
                        VALUES ( '" . $docointkey . "', '" . $enccode . "', '" . $this->hpercode . "', '1', '1', '" . $dmdcomb . "', '1', 'A',
                            'N', 'N', 'N', '" . $dmdctr . "', 'U', '" . auth()->user()->employeeid . "', 'NEWOR', 'ACTIV', 'PHARM', '" . $chrgcode . "', 'c',
                            '" . ($this->type ? true : false) . "', '" . $this->type . "', '" . ($this->is_ris ? true : false) . "', '" . $this->order_qty . "', '" . $this->unit_price . "',
                            '" . $this->order_qty * $this->unit_price . "', '" . now() . "', '" . now() . "', '" . now() . "', '" . now() . "', '" . $dmdprdte . "', '" . $exp_date . "',
                            '" . $loc_code . "', '" . $id . "', '" . ($this->remarks ?? '') . "', '" . $rx_id . "', '" . $empid . "' )");

            if ($with_rx) {
                DB::connection('webapp')->table('webapp.dbo.prescription_data')
                    ->where('id', $rx_id)
                    ->update(['stat' => 'I']);
            }

            $this->showAddItemModal = false;
            $this->resetExcept(
                'code',
                'enccode',
                'encdate',
                'hpercode',
                'toecode',
                'mssikey',
                'patlast',
                'patfirst',
                'patmiddle',
                'diagtext',
                'wardname',
                'rmname',
                'billstat',
                'location_id',
                'hasEncounter',
                'encounter',
                'charges',
                'generic',
                'rx_dmdcomb',
                'rx_dmdctr',
                'rx_id',
                'empid',
                'stocks',
                'selected_items',
                'patient',
                'charge_code_filter',
                'stocksDisplayCount',
                'active_prescription',
                'active_prescription_all',
                'extra_prescriptions',
                'extra_prescriptions_all',
                'adm',
                'summaries',
            );
            $this->success('Item added.');
        } else {
            $this->error('Insufficient stock!');
        }
    }

    public function add_prescribed_item()
    {
        $this->validate([
            'order_qty' => ['required', 'numeric', 'min:1'],
            'rx_charge_code' => ['required'],
        ]);

        $dmdcomb = $this->rx_dmdcomb;
        $dmdctr = $this->rx_dmdctr;
        $rx_id = $this->rx_id;
        $empid = $this->empid;

        $this->type = $this->resolveTransactionType();

        $dm = DrugStock::where('dmdcomb', $dmdcomb)
            ->where('dmdctr', $dmdctr)
            ->where('chrgcode', $this->rx_charge_code)
            ->where('loc_code', $this->location_id)
            ->where('stock_bal', '>', '0')
            ->orderBy('exp_date', 'ASC')
            ->first();

        if ($dm) {
            $enccode = $this->decryptEnccode();

            DrugOrder::create([
                'docointkey' => '0000040' . $this->hpercode . date('m/d/Yh:i:s', strtotime(now())) . $dm->chrgcode . $dm->dmdcomb . $dm->dmdctr,
                'enccode' => $enccode,
                'hpercode' => $this->hpercode,
                'rxooccid' => '1',
                'rxoref' => '1',
                'dmdcomb' => $dm->dmdcomb,
                'repdayno1' => '1',
                'rxostatus' => 'A',
                'rxolock' => 'N',
                'rxoupsw' => 'N',
                'rxoconfd' => 'N',
                'dmdctr' => $dm->dmdctr,
                'estatus' => 'U',
                'entryby' => auth()->user()->employeeid,
                'ordcon' => 'NEWOR',
                'orderupd' => 'ACTIV',
                'locacode' => 'PHARM',
                'orderfrom' => $dm->chrgcode,
                'issuetype' => 'c',
                'has_tag' => $this->type ? true : false,
                'tx_type' => $this->type,
                'ris' => $this->is_ris ? true : false,
                'pchrgqty' => $this->order_qty,
                'pchrgup' => $dm->current_price->dmselprice,
                'pcchrgamt' => $this->order_qty * $dm->current_price->dmselprice,
                'dodate' => now(),
                'dotime' => now(),
                'dodtepost' => now(),
                'dotmepost' => now(),
                'dmdprdte' => $dm->dmdprdte,
                'exp_date' => $dm->exp_date,
                'loc_code' => $dm->loc_code,
                'item_id' => $dm->id,
                'remarks' => $this->remarks,
                'prescription_data_id' => $rx_id,
                'prescribed_by' => $empid,
            ]);

            DB::connection('webapp')->table('webapp.dbo.prescription_data')
                ->where('id', $rx_id)
                ->update(['stat' => 'I']);

            $this->showPrescribedItemModal = false;
            $this->resetExcept(
                'code',
                'enccode',
                'encdate',
                'hpercode',
                'toecode',
                'mssikey',
                'patlast',
                'patfirst',
                'patmiddle',
                'diagtext',
                'wardname',
                'rmname',
                'billstat',
                'location_id',
                'hasEncounter',
                'encounter',
                'charges',
                'generic',
                'stocks',
                'selected_items',
                'patient',
                'charge_code_filter',
                'stocksDisplayCount',
                'active_prescription',
                'active_prescription_all',
                'extra_prescriptions',
                'extra_prescriptions_all',
                'adm',
                'summaries',
            );
            $this->success('Item added.');
        } else {
            $this->error('Insufficient stock!');
        }
    }

    public function delete_item()
    {
        if (empty($this->selected_items)) {
            $this->warning('No items selected.');
            return;
        }

        $placeholders = implode(',', array_fill(0, count($this->selected_items), '?'));

        DB::delete(
            "DELETE FROM hrxo
                    WHERE docointkey IN ($placeholders)
                    AND (estatus = 'U' OR pcchrgcod IS NULL)",
            $this->selected_items
        );

        $this->reset('selected_items');
        $this->success('Selected item/s deleted!');
    }

    public function return_issued(DrugOrder $item)
    {
        $this->validate([
            'return_qty' => ['required', 'numeric', 'min:1', 'max:' . $item->qtyissued],
            'unit_price' => 'required',
            'docointkey' => 'required',
        ]);

        $isReturned = DrugOrderReturn::where('docointkey', $this->docointkey)->count();

        if ($isReturned) {
            $this->error('Item already returned.');
            return;
        }

        $employeeid = auth()->user()->employeeid;

        DB::insert("INSERT INTO hospital.dbo.hrxoreturn(
                docointkey, enccode, hpercode, dmdcomb, returndate, returntime, qty, returnby,
                status, rxolock, updsw, confdl, entryby, locacode, dmdctr, dmdprdte, remarks,
                returnfrom, chrgcode, pcchrgcod, rcode, unitprice, pchrgup, loc_code)
            VALUES(
            '" . $item->docointkey . "',
            '" . $item->enccode . "',
            '" . $item->hpercode . "',
            '" . $item->dmdcomb . "',
            '" . now() . "',
            '" . now() . "',
            '" . $this->return_qty . "',
            '" . $employeeid . "',
            'A', 'N', 'N', 'N',
            '" . $employeeid . "',
            '" . $item->locacode . "',
            '" . $item->dmdctr . "',
            '" . $item->dmdprdte . "',
            '" . $item->remarks . "',
            '" . $item->orderfrom . "',
            '" . $item->orderfrom . "',
            '" . $item->pcchrgcod . "',
            '',
            '" . $item->pchrgup . "',
            '" . $item->pchrgup . "',
            '" . $this->location_id . "'
            )
        ");

        $item->pcchrgamt = $item->pchrgup * ($item->qtyissued - $this->return_qty);
        $item->qtyissued -= $this->return_qty;
        $item->save();

        $issued_items = DrugStockIssue::where('docointkey', $this->docointkey)->with('stock')->latest()->get();
        $qty_to_return = $this->return_qty;

        foreach ($issued_items as $stock_issued) {
            if ($qty_to_return > $stock_issued->qty) {
                $returned_qty = $stock_issued->qty;
                $qty_to_return -= $stock_issued->qty;
                $stock_issued->returned_qty = $stock_issued->qty;
                $stock_issued->qty = 0;
            } else {
                $returned_qty = $qty_to_return;
                $stock_issued->qty -= $qty_to_return;
                $stock_issued->returned_qty = $qty_to_return;
                $qty_to_return = 0;
                $stock_issued->qty = 0;
            }

            $stock = DrugStock::firstOrCreate([
                'dmdcomb' => $item->dmdcomb,
                'dmdctr' => $item->dmdctr,
                'loc_code' => $this->location_id,
                'chrgcode' => $stock_issued->chrgcode,
                'exp_date' => $stock_issued->exp_date,
                'retail_price' => $item->pchrgup,
                'drug_concat' => $stock_issued->stock->drug_concat,
                'dmdnost' => $stock_issued->stock->dmdnost,
                'strecode' => $stock_issued->stock->strecode,
                'formcode' => $stock_issued->stock->formcode,
                'rtecode' => $stock_issued->stock->rtecode,
                'brandname' => $stock_issued->stock->brandname,
                'dmdrem' => $stock_issued->stock->dmdrem,
                'dmdrxot' => $stock_issued->stock->dmdrxot,
                'gencode' => $stock_issued->stock->gencode,
                'dmdprdte' => $stock_issued->stock->dmdprdte,
            ]);
            $stock->stock_bal = $stock->stock_bal + $this->return_qty;

            $date = Carbon::parse(now())->format('Y-m-d');

            $log = DrugStockLog::firstOrNew([
                'loc_code' => $this->location_id,
                'dmdcomb' => $stock_issued->stock->dmdcomb,
                'dmdctr' => $stock_issued->stock->dmdctr,
                'chrgcode' => $stock_issued->stock->chrgcode,
                'unit_cost' => $stock_issued->stock->current_price ? $stock_issued->stock->current_price->acquisition_cost : 0,
                'unit_price' => $stock_issued->stock->retail_price,
                'consumption_id' => null,
            ]);
            $log->return_qty += $returned_qty;

            $drug_concat = implode("", explode('_', $stock_issued->stock->drug_concat));
            $card = DrugStockCard::firstOrNew([
                'chrgcode' => $log->chrgcode,
                'loc_code' => $log->loc_code,
                'dmdcomb' => $log->dmdcomb,
                'dmdctr' => $log->dmdctr,
                'exp_date' => $stock_issued->stock->exp_date,
                'stock_date' => $date,
                'drug_concat' => $drug_concat,
                'dmdprdte' => $stock_issued->stock->dmdprdte,
                'io_trans_ref_no' => $item->pcchrgcod,
            ]);
            $card->rec += $returned_qty;
            $card->bal += $returned_qty;

            $card->save();
            $log->save();
            $stock->save();
            $stock_issued->save();
        }

        $this->showReturnModal = false;
        $this->success('Item returned.');
    }

    public function update_remarks()
    {
        $this->validate(['selected_remarks' => ['required'], 'new_remarks' => ['nullable', 'string', 'max:255']]);
        $rxo = DrugOrder::find($this->selected_remarks);
        $rxo->remarks = $this->new_remarks;
        $rxo->save();
        $this->reset('selected_remarks', 'new_remarks');
        $this->success('Remarks updated');
    }

    public function update_qty()
    {
        $this->validate([
            'order_qty' => ['required', 'numeric', 'min:1'],
        ]);
        DrugOrder::where('docointkey', $this->docointkey)
            ->update([
                'pchrgqty' => $this->order_qty,
                'pchrgup' => $this->unit_price,
                'pcchrgamt' => $this->order_qty * $this->unit_price
            ]);
        $this->showUpdateQtyModal = false;
        $this->success('Order updated!');
    }

    public function deactivate_rx()
    {
        $data = PrescriptionData::find($this->rx_id);
        $data->stat = 'I';
        $data->addtl_remarks = $this->adttl_remarks;
        $data->save();
        $this->showDeactivateRxModal = false;
        $this->success('Prescription deactivated!');
    }

    public function reactivate_rx($rxId)
    {
        $data = PrescriptionData::find($rxId);
        if ($data) {
            $data->stat = 'A';
            $data->save();
            $this->success('Prescription reactivated!');
        }
    }

    #[On('updateSelectedItems')]
    public function updateSelectedItems($items)
    {
        $this->selected_items = $items;
    }

    public function loadMoreStocks()
    {
        $this->stocksDisplayCount += 50;
    }

    public function updatedChargeCodeFilter()
    {
        $this->stocksDisplayCount = 50;
    }

    public function updatedGeneric()
    {
        $this->stocksDisplayCount = 50;
    }

    // ──────────────────────────────────────────────
    // Encounter / Prescription Selector
    // ──────────────────────────────────────────────

    public function openEncounterSelector()
    {
        $this->reset('selector_search_hpercode', 'selector_search_lastname', 'selector_search_firstname', 'selector_patient_results');

        if ($this->hpercode) {
            $this->selector_selected_hpercode = $this->hpercode;
            $this->selector_patient_name = trim($this->patlast . ', ' . $this->patfirst . ' ' . $this->patmiddle);
            $this->loadPatientEncountersList();
        } else {
            $this->selector_selected_hpercode = null;
            $this->selector_patient_name = null;
            $this->patient_encounters = [];
        }

        $this->showEncounterSelectorModal = true;
    }

    public function openChangePatient()
    {
        $this->reset('selector_search_hpercode', 'selector_search_lastname', 'selector_search_firstname', 'selector_patient_results');
        $this->selector_selected_hpercode = null;
        $this->selector_patient_name = null;
        $this->patient_encounters = [];
        $this->reset('selected_encounter_code', 'selected_encounter_prescriptions');

        $this->showEncounterSelectorModal = true;
    }

    public function selectorSearchPatients()
    {
        $query = Patient::query();

        if ($this->selector_search_hpercode) {
            $query->where('hpercode', 'LIKE', $this->selector_search_hpercode . '%');
        }
        if ($this->selector_search_lastname) {
            $query->where('patlast', 'LIKE', $this->selector_search_lastname . '%');
        }
        if ($this->selector_search_firstname) {
            $query->where('patfirst', 'LIKE', $this->selector_search_firstname . '%');
        }

        if (!$this->selector_search_hpercode && !$this->selector_search_lastname && !$this->selector_search_firstname) {
            $this->warning('Please enter at least one search criteria.');
            return;
        }

        $this->selector_patient_results = $query->orderBy('patlast')->orderBy('patfirst')->limit(50)->get();

        if ($this->selector_patient_results->isEmpty()) {
            $this->warning('No patients found.');
        }
    }

    public function selectorSelectPatient($hpercode)
    {
        $patient = Patient::where('hpercode', $hpercode)->first();
        if (!$patient) return;

        $this->selector_selected_hpercode = $hpercode;
        $this->selector_patient_name = trim($patient->patlast . ', ' . $patient->patfirst . ' ' . $patient->patmiddle);
        $this->selector_patient_results = [];

        $this->loadPatientEncountersList();
    }

    public function selectorClearPatient()
    {
        $this->selector_selected_hpercode = null;
        $this->selector_patient_name = null;
        $this->patient_encounters = [];
        $this->reset('selected_encounter_code', 'selected_encounter_prescriptions', 'selected_encounter_orders', 'encounter_detail_tab');
    }

    public function loadPatientEncountersList()
    {
        $hpercode = $this->selector_selected_hpercode;
        if (!$hpercode) return;

        $filter = $this->encounter_area_filter;

        $toecodeFilter = match ($filter) {
            'ward' => "AND enctr.toecode IN ('ADM', 'OPDAD', 'ERADM')",
            'er' => "AND enctr.toecode = 'ER'",
            'opd' => "AND enctr.toecode = 'OPD'",
            default => "AND enctr.toecode != 'WALKN'",
        };

        $this->patient_encounters = collect(DB::select("
            SELECT TOP 20
                enctr.enccode,
                enctr.toecode,
                enctr.encdate,
                ward.wardname,
                room.rmname,
                diag.diagtext,
                track.billstat,
                (SELECT COUNT(*) FROM webapp.dbo.prescription rx WITH (NOLOCK)
                    INNER JOIN webapp.dbo.prescription_data rd WITH (NOLOCK) ON rx.id = rd.presc_id
                    WHERE rx.enccode = enctr.enccode AND rd.stat = 'A') AS active_rx_count,
                (SELECT COUNT(*) FROM hospital.dbo.hrxo WITH (NOLOCK)
                    WHERE hrxo.enccode = enctr.enccode) AS order_count
            FROM hospital.dbo.henctr enctr WITH (NOLOCK)
                LEFT JOIN hospital.dbo.hactrack track WITH (NOLOCK) ON enctr.enccode = track.enccode
                LEFT JOIN hospital.dbo.hencdiag diag WITH (NOLOCK) ON enctr.enccode = diag.enccode
                LEFT JOIN hospital.dbo.hpatroom patroom WITH (NOLOCK) ON enctr.enccode = patroom.enccode
                LEFT JOIN hospital.dbo.hward ward WITH (NOLOCK) ON patroom.wardcode = ward.wardcode
                LEFT JOIN hospital.dbo.hroom room WITH (NOLOCK) ON patroom.rmintkey = room.rmintkey
            WHERE enctr.hpercode = ?
                {$toecodeFilter}
            ORDER BY enctr.encdate DESC
        ", [$hpercode]))->all();

        $this->reset('selected_encounter_code', 'selected_encounter_prescriptions', 'selected_encounter_orders', 'encounter_detail_tab');
    }

    public function updatedEncounterAreaFilter()
    {
        $this->loadPatientEncountersList();
    }

    public function selectEncounterPrescriptions($enccode)
    {
        $this->selected_encounter_code = $enccode;

        $this->selected_encounter_prescriptions = Prescription::where('enccode', $enccode)
            ->with('data_active')
            ->has('data_active')
            ->get();

        $this->selected_encounter_orders = DB::select("
            SELECT hrxo.docointkey, hrxo.pcchrgcod, hrxo.dodate, hrxo.pchrgqty, hrxo.estatus,
                   hrxo.qtyissued, hrxo.pchrgup, hrxo.pcchrgamt, hdmhdr.drug_concat,
                   hcharge.chrgdesc, hrxo.remarks, hrxo.tx_type, hrxo.prescription_data_id,
                   hrxo.dmdcomb, hrxo.dmdctr
            FROM hospital.dbo.hrxo WITH (NOLOCK)
            INNER JOIN hospital.dbo.hdmhdr ON hdmhdr.dmdcomb = hrxo.dmdcomb AND hdmhdr.dmdctr = hrxo.dmdctr
            INNER JOIN hospital.dbo.hcharge ON hrxo.orderfrom = hcharge.chrgcode
            WHERE hrxo.enccode = ?
            ORDER BY hrxo.dodate DESC
        ", [$enccode]);
    }

    public function initiateWalkIn()
    {
        if (!$this->selector_selected_hpercode) {
            $this->error('Please select a patient first.');
            return;
        }

        // Check for existing walk-in encounter
        $existingWalkIn = EncounterLog::where('encstat', 'W')
            ->where('toecode', 'WALKN')
            ->where('hpercode', $this->selector_selected_hpercode)
            ->latest('encdate')
            ->first();

        if ($existingWalkIn) {
            $this->navigateToEncounter($existingWalkIn->enccode);
            return;
        }

        // Create new walk-in encounter
        $newEnccode = '0000040' . $this->selector_selected_hpercode . date('mdYHis');

        $newEncounter = EncounterLog::create([
            'enccode' => $newEnccode,
            'fhud' => '0000040',
            'hpercode' => $this->selector_selected_hpercode,
            'encdate' => now(),
            'enctime' => now(),
            'toecode' => 'WALKN',
            'sopcode1' => 'SELPA',
            'encstat' => 'W',
            'confdl' => 'N',
        ]);

        $this->success('Walk-in encounter created successfully.');
        $this->navigateToEncounter($newEncounter->enccode);
    }

    public function navigateToEncounter($enccode)
    {
        $encrypted = Crypt::encrypt(str_replace(' ', '--', $enccode));
        return redirect()->route('dispensing.view.enctr', ['enccode' => $encrypted]);
    }

    public function addPrescriptionFromEncounter($rxId, $dmdcomb, $dmdctr, $empid, $qty)
    {
        if (!$this->hasEncounter) {
            $this->warning('Please navigate to an encounter first before adding prescriptions.');
            return;
        }

        $this->rx_id = $rxId;
        $this->rx_dmdcomb = $dmdcomb;
        $this->rx_dmdctr = $dmdctr;
        $this->empid = $empid;
        $this->order_qty = $qty;

        if ($this->toecode == 'OPD' || $this->toecode == 'WALKN') {
            $this->loadAvailableCharges($dmdcomb, $dmdctr);
            $this->showEncounterSelectorModal = false;
            $this->showPrescribedItemModal = true;
        } else {
            $this->generic = Drug::select('drug_concat')
                ->where('dmdcomb', $dmdcomb)
                ->where('dmdctr', $dmdctr)
                ->first()?->drug_concat ?? '';

            if ($this->generic) {
                $this->generic = explode(',', $this->generic)[0];
            }

            $this->showEncounterSelectorModal = false;
        }
    }

    // ──────────────────────────────────────────────
    // Rx/Orders Browsing (Area-Based)
    // ──────────────────────────────────────────────

    public function switchSelectorMode($mode)
    {
        $this->selector_mode = $mode;
        if ($mode === 'rx_orders') {
            $this->loadRxBrowseResults();
        }
    }

    public function setRxBrowseArea($area)
    {
        $this->rx_browse_area = $area;
        $this->rx_browse_search = '';
        $this->rx_browse_tag_filter = 'all';
        $this->rx_browse_wardcode = '';
        $this->loadRxBrowseResults();
    }

    public function setRxBrowseTagFilter($filter)
    {
        $this->rx_browse_tag_filter = $filter;
    }

    public function updatedRxBrowseDate()
    {
        $this->loadRxBrowseResults();
    }

    public function updatedRxBrowseWardcode()
    {
        $this->loadRxBrowseResults();
    }

    public function loadRxBrowseResults()
    {
        $area = $this->rx_browse_area;

        if ($area === 'opd') {
            $this->loadRxBrowseOpd();
        } elseif ($area === 'ward') {
            $this->loadRxBrowseWard();
        } elseif ($area === 'er') {
            $this->loadRxBrowseEr();
        }
    }

    private function loadRxBrowseOpd(): void
    {
        $from = Carbon::parse($this->rx_browse_date)->startOfDay();
        $to = Carbon::parse($this->rx_browse_date)->endOfDay();

        $this->rx_browse_results = DB::select("
            SELECT
                enctr.enccode, opd.opddate, opd.opdtime, enctr.hpercode,
                pt.patfirst, pt.patmiddle, pt.patlast, pt.patsuffix,
                mss.mssikey, ser.tsdesc,
                (SELECT COUNT(qty) FROM webapp.dbo.prescription_data data WITH (NOLOCK)
                    WHERE rx.id = data.presc_id AND data.stat = 'A'
                    AND (data.order_type = '' OR data.order_type IS NULL)) AS basic,
                (SELECT COUNT(qty) FROM webapp.dbo.prescription_data data WITH (NOLOCK)
                    WHERE rx.id = data.presc_id AND data.stat = 'A' AND data.order_type = 'G24') AS g24,
                (SELECT COUNT(qty) FROM webapp.dbo.prescription_data data WITH (NOLOCK)
                    WHERE rx.id = data.presc_id AND data.stat = 'A' AND data.order_type = 'OR') AS 'or'
            FROM hospital.dbo.henctr enctr WITH (NOLOCK)
                RIGHT JOIN webapp.dbo.prescription rx WITH (NOLOCK) ON enctr.enccode = rx.enccode
                LEFT JOIN hospital.dbo.hopdlog opd WITH (NOLOCK) ON enctr.enccode = opd.enccode
                RIGHT JOIN hospital.dbo.hperson pt WITH (NOLOCK) ON enctr.hpercode = pt.hpercode
                LEFT JOIN hospital.dbo.hpatmss mss WITH (NOLOCK) ON enctr.enccode = mss.enccode
                LEFT JOIN hospital.dbo.htypser ser WITH (NOLOCK) ON opd.tscode = ser.tscode
            WHERE opdtime BETWEEN ? AND ?
                AND toecode = 'OPD' AND rx.stat = 'A'
            ORDER BY pt.patlast ASC, pt.patfirst ASC, pt.patmiddle ASC, rx.created_at DESC
        ", [$from, $to]);
    }

    private function loadRxBrowseWard(): void
    {
        $wardFilter = $this->rx_browse_wardcode ? "AND ward.wardcode = ?" : "";
        $params = $this->rx_browse_wardcode ? [$this->rx_browse_wardcode] : [];

        $this->rx_browse_results = DB::select("
            SELECT
                enctr.enccode, adm.admdate, enctr.hpercode,
                pt.patfirst, pt.patmiddle, pt.patlast, pt.patsuffix,
                room.rmname, ward.wardname, ward.wardcode, mss.mssikey,
                (SELECT COUNT(qty) FROM webapp.dbo.prescription_data data WITH (NOLOCK)
                    WHERE rx.id = data.presc_id AND data.stat = 'A'
                    AND (data.order_type = '' OR data.order_type IS NULL)) AS basic,
                (SELECT COUNT(qty) FROM webapp.dbo.prescription_data data WITH (NOLOCK)
                    WHERE rx.id = data.presc_id AND data.stat = 'A' AND data.order_type = 'G24') AS g24,
                (SELECT COUNT(qty) FROM webapp.dbo.prescription_data data WITH (NOLOCK)
                    WHERE rx.id = data.presc_id AND data.stat = 'A' AND data.order_type = 'OR') AS 'or'
            FROM hospital.dbo.henctr enctr WITH (NOLOCK)
                RIGHT JOIN webapp.dbo.prescription rx WITH (NOLOCK) ON enctr.enccode = rx.enccode
                LEFT JOIN hospital.dbo.hadmlog adm WITH (NOLOCK) ON enctr.enccode = adm.enccode
                RIGHT JOIN hospital.dbo.hpatroom pat_room WITH (NOLOCK) ON rx.enccode = pat_room.enccode
                RIGHT JOIN hospital.dbo.hroom room WITH (NOLOCK) ON pat_room.rmintkey = room.rmintkey
                RIGHT JOIN hospital.dbo.hward ward WITH (NOLOCK) ON pat_room.wardcode = ward.wardcode
                RIGHT JOIN hospital.dbo.hperson pt WITH (NOLOCK) ON enctr.hpercode = pt.hpercode
                LEFT JOIN hospital.dbo.hpatmss mss WITH (NOLOCK) ON enctr.enccode = mss.enccode
            WHERE (toecode = 'ADM' OR toecode = 'OPDAD' OR toecode = 'ERADM')
                AND pat_room.patrmstat = 'A' AND rx.stat = 'A'
                {$wardFilter}
            ORDER BY pt.patlast ASC, pt.patfirst ASC, pt.patmiddle ASC, rx.created_at DESC
        ", $params);
    }

    private function loadRxBrowseEr(): void
    {
        $from = Carbon::parse($this->rx_browse_date)->subDay()->startOfDay();
        $to = Carbon::parse($this->rx_browse_date)->endOfDay();

        $this->rx_browse_results = DB::select("
            SELECT
                enctr.enccode, er.erdate, er.ertime, enctr.hpercode,
                pt.patfirst, pt.patmiddle, pt.patlast, pt.patsuffix,
                ser.tsdesc, mss.mssikey,
                (SELECT COUNT(qty) FROM webapp.dbo.prescription_data data WITH (NOLOCK)
                    WHERE rx.id = data.presc_id AND data.stat = 'A'
                    AND (data.order_type = '' OR data.order_type IS NULL)) AS basic,
                (SELECT COUNT(qty) FROM webapp.dbo.prescription_data data WITH (NOLOCK)
                    WHERE rx.id = data.presc_id AND data.stat = 'A' AND data.order_type = 'G24') AS g24,
                (SELECT COUNT(qty) FROM webapp.dbo.prescription_data data WITH (NOLOCK)
                    WHERE rx.id = data.presc_id AND data.stat = 'A' AND data.order_type = 'OR') AS 'or'
            FROM hospital.dbo.henctr enctr WITH (NOLOCK)
                LEFT JOIN webapp.dbo.prescription rx WITH (NOLOCK) ON enctr.enccode = rx.enccode
                LEFT JOIN hospital.dbo.herlog er WITH (NOLOCK) ON enctr.enccode = er.enccode
                LEFT JOIN hospital.dbo.hperson pt WITH (NOLOCK) ON enctr.hpercode = pt.hpercode
                LEFT JOIN hospital.dbo.htypser ser WITH (NOLOCK) ON er.tscode = ser.tscode
                LEFT JOIN hospital.dbo.hpatmss mss WITH (NOLOCK) ON enctr.enccode = mss.enccode
            WHERE erdate BETWEEN ? AND ?
                AND toecode = 'ER' AND erstat = 'A'
            ORDER BY pt.patlast ASC, pt.patfirst ASC, pt.patmiddle ASC
        ", [$from, $to]);
    }

    public function rxBrowseSelectEncounter($enccode)
    {
        $encrypted = Crypt::encrypt(str_replace(' ', '--', $enccode));
        return redirect()->route('dispensing.view.enctr', ['enccode' => $encrypted]);
    }

    // ──────────────────────────────────────────────
    // Helper Methods (Extracted & Reusable)
    // ──────────────────────────────────────────────

    private function loadAvailableCharges(string $dmdcomb, string $dmdctr): void
    {
        $this->rx_charge_code = null;

        $this->rx_available_charges = DB::select("
            SELECT
                pharm_drug_stocks.chrgcode,
                hcharge.chrgdesc,
                SUM(pharm_drug_stocks.stock_bal) AS stock_bal
            FROM hospital.dbo.pharm_drug_stocks WITH (NOLOCK)
            INNER JOIN hospital.dbo.hcharge ON hcharge.chrgcode = pharm_drug_stocks.chrgcode
            WHERE pharm_drug_stocks.dmdcomb = ?
                AND pharm_drug_stocks.dmdctr = ?
                AND pharm_drug_stocks.loc_code = ?
                AND pharm_drug_stocks.stock_bal > 0
            GROUP BY pharm_drug_stocks.chrgcode, hcharge.chrgdesc
            ORDER BY hcharge.chrgdesc
        ", [$dmdcomb, $dmdctr, $this->location_id]);
    }

    private function decryptEnccode(): string
    {
        return str_replace('--', ' ', Crypt::decrypt($this->enccode));
    }

    private function isAdmittedEncounter(): bool
    {
        return in_array($this->toecode, ['ADM', 'OPDAD', 'ERADM']);
    }

    private function resolveTransactionType(): string
    {
        if ($this->isAdmittedEncounter()) {
            return $this->bnb ? 'pay' : 'service';
        }

        return match (true) {
            $this->ems => 'ems',
            $this->maip => 'maip',
            $this->wholesale => 'wholesale',
            (bool) $this->service => 'service',
            $this->caf => 'caf',
            $this->is_ris => 'ris',
            $this->pcso => 'pcso',
            $this->phic => 'phic',
            $this->konsulta => 'konsulta',
            $this->doh_free => 'doh_free',
            default => 'opdpay',
        };
    }

    public function getMssClassification(): string
    {
        return match ($this->mssikey) {
            'MSSA11111999', 'MSSB11111999' => 'Pay',
            'MSSC111111999' => 'PP1',
            'MSSC211111999' => 'PP2',
            'MSSC311111999' => 'PP3',
            'MSSD11111999' => 'Indigent',
            default => '---',
        };
    }

    private function resetIssueFlags(): void
    {
        $this->ems = false;
        $this->maip = false;
        $this->wholesale = false;
        $this->caf = false;
        $this->konsulta = false;
        $this->pcso = false;
        $this->phic = false;
        $this->pay = false;
        $this->service = false;
        $this->doh_free = false;
        $this->is_ris = false;
        $this->bnb = false;
        $this->deptcode = null;
    }

    private function loadPrescriptions(string $enccode, $encounter): void
    {
        $this->active_prescription = Prescription::where('enccode', $enccode)->has('data_active')->get();
        $this->active_prescription_all = Prescription::where('enccode', $enccode)->with('data')->get();

        $past_log = match ($encounter->toecode) {
            'ADM' => EncounterLog::where('hpercode', $encounter->hpercode)
                ->whereIn('toecode', ['ERADM', 'OPDAD'])
                ->latest('encdate')->first(),
            'OPDAD' => EncounterLog::where('hpercode', $encounter->hpercode)
                ->where('toecode', 'OPD')
                ->latest('encdate')->first(),
            'ERADM' => EncounterLog::where('hpercode', $encounter->hpercode)
                ->where('toecode', 'ER')
                ->latest('encdate')->first(),
            default => null,
        };

        if ($past_log) {
            $this->extra_prescriptions = Prescription::where('enccode', $past_log->enccode)->with('data_active')->has('data_active')->get();
            $this->extra_prescriptions_all = Prescription::where('enccode', $past_log->enccode)->with('data')->get();
        }
    }

    private function fetchOrders(string $enccode): array
    {
        if ($this->toecode == 'WALKN') {
            return DB::select("SELECT docointkey, pcchrgcod, dodate, pchrgqty, estatus, qtyissued, pchrgup, pcchrgamt, drug_concat, chrgdesc, remarks, mssikey, tx_type, prescription_data_id
                                FROM henctr enctr
                                INNER JOIN hospital.dbo.hrxo ON enctr.enccode = hrxo.enccode
                                INNER JOIN hdmhdr ON hdmhdr.dmdcomb = hrxo.dmdcomb AND hdmhdr.dmdctr = hrxo.dmdctr
                                INNER JOIN hcharge ON orderfrom = chrgcode
                                LEFT JOIN hpatmss ON hrxo.enccode = hpatmss.enccode
                                WHERE hrxo.hpercode = '" . $this->hpercode . "' AND enctr.toecode = 'WALKN'
                                ORDER BY dodate DESC");
        }

        return DB::select("SELECT docointkey, pcchrgcod, dodate, pchrgqty, estatus, qtyissued, pchrgup, pcchrgamt, drug_concat, chrgdesc, remarks, mssikey, tx_type, prescription_data_id
                            FROM hospital.dbo.hrxo
                            INNER JOIN hdmhdr ON hdmhdr.dmdcomb = hrxo.dmdcomb AND hdmhdr.dmdctr = hrxo.dmdctr
                            INNER JOIN hcharge ON orderfrom = chrgcode
                            LEFT JOIN hpatmss ON hrxo.enccode = hpatmss.enccode
                            WHERE hrxo.enccode = '" . $enccode . "'
                            ORDER BY dodate DESC");
    }

    private function fetchStocks(): array
    {
        $chargeCodeFilter = '';
        if (!empty($this->charge_code_filter)) {
            $chargeCodeList = "'" . implode("','", $this->charge_code_filter) . "'";
            $chargeCodeFilter = " AND pharm_drug_stocks.chrgcode IN ($chargeCodeList)";
        }

        $genericFilter = '';
        if ($this->generic) {
            $genericFilter = " AND pharm_drug_stocks.drug_concat LIKE '%" . implode("''", explode("'", $this->generic)) . "%'";
        }

        return DB::select("SELECT TOP " . $this->stocksDisplayCount . "
                            pharm_drug_stocks.dmdcomb, pharm_drug_stocks.dmdctr, pharm_drug_stocks.drug_concat, hcharge.chrgdesc,
                            pharm_drug_stocks.chrgcode, hdmhdrprice.retail_price, dmselprice, pharm_drug_stocks.loc_code,
                            pharm_drug_stocks.dmdprdte as dmdprdte, SUM(stock_bal) as stock_bal, MAX(id) as id, MIN(exp_date) as exp_date,
                            hdmhdrprice.acquisition_cost, DATEDIFF(day, GETDATE(), MIN(exp_date)) as days_to_expiry
                        FROM hospital.dbo.pharm_drug_stocks WITH (NOLOCK)
                        INNER JOIN hcharge on hcharge.chrgcode = pharm_drug_stocks.chrgcode
                        INNER JOIN hdmhdrprice on hdmhdrprice.dmdprdte = pharm_drug_stocks.dmdprdte
                        WHERE loc_code = '" . $this->location_id . "'
                        $genericFilter
                        $chargeCodeFilter
                        AND stock_bal > 0
                        GROUP BY pharm_drug_stocks.dmdcomb, pharm_drug_stocks.dmdctr, pharm_drug_stocks.chrgcode,
                                 hdmhdrprice.retail_price, hdmhdrprice.acquisition_cost, dmselprice,
                                 pharm_drug_stocks.drug_concat, hcharge.chrgdesc, pharm_drug_stocks.loc_code, pharm_drug_stocks.dmdprdte
                        ORDER BY pharm_drug_stocks.drug_concat");
    }

    private function fetchStocksForIssue($rxo): array
    {
        return DB::select(
            "SELECT pharm_drug_stocks.*, hdmhdrprice.dmduprice
                FROM pharm_drug_stocks
                JOIN hdmhdrprice ON pharm_drug_stocks.dmdprdte = hdmhdrprice.dmdprdte
            WHERE pharm_drug_stocks.dmdcomb = '" . $rxo->dmdcomb . "'
                AND pharm_drug_stocks.dmdctr = '" . $rxo->dmdctr . "'
                AND pharm_drug_stocks.chrgcode = '" . $rxo->orderfrom . "'
                AND pharm_drug_stocks.loc_code = '" . $this->location_id . "'
                AND pharm_drug_stocks.exp_date > '" . date('Y-m-d') . "'
                AND pharm_drug_stocks.stock_bal > 0
            ORDER BY pharm_drug_stocks.exp_date ASC"
        );
    }

    private function logHrxoIssue($docointkey, $enccode, $hpercode, $dmdcomb, $dmdctr, $pchrgqty, $employeeid, $orderfrom, $pcchrgcod, $pchrgup, $ris, $prescription_data_id, $date, $dmdprdte): void
    {
        if ($prescription_data_id) {
            PrescriptionDataIssued::create([
                'presc_data_id' => $prescription_data_id,
                'docointkey' => $docointkey,
                'qtyissued' => $pchrgqty,
            ]);
        } else {
            $rx_header = Prescription::where('enccode', $enccode)->with('data_active')->get();
            if ($rx_header) {
                foreach ($rx_header as $rxh) {
                    $rx_data = $rxh->data_active()
                        ->where('dmdcomb', $dmdcomb)
                        ->where('dmdctr', $dmdctr)
                        ->first();
                    if ($rx_data) {
                        PrescriptionDataIssued::create([
                            'presc_data_id' => $rx_data->id,
                            'docointkey' => $docointkey,
                            'qtyissued' => $pchrgqty,
                        ]);

                        DB::update(
                            "UPDATE hospital.dbo.hrxo SET prescription_data_id = ?, prescribed_by = ? WHERE docointkey = ?",
                            [$rx_data->id, $rx_data->entry_by, $docointkey]
                        );

                        $rx_data->stat = 'I';
                        $rx_data->save();
                    }
                }
            }
        }
    }

    // ──────────────────────────────────────────────
    // Queue Integration Methods
    // ──────────────────────────────────────────────

    private function loadQueueContext($queueId): void
    {
        $queue = PrescriptionQueue::find($queueId);
        if ($queue) {
            $this->queueId = $queue->id;
            $this->currentQueueNumber = $queue->queue_number;
            $this->currentQueueStatus = $queue->queue_status;
            $this->queueChargeSlipNo = $queue->charge_slip_no;
            $this->showQueuePanel = true;
            $this->loadQueueList();
        }
    }

    private function autoDetectQueueByEnccode(string $enccode): void
    {
        $queue = PrescriptionQueue::where('enccode', $enccode)
            ->where('location_code', auth()->user()->pharm_location_id)
            ->whereIn('queue_status', ['waiting', 'preparing', 'charging', 'ready'])
            ->whereDate('queued_at', today())
            ->first();

        if ($queue) {
            $this->queueId = $queue->id;
            $this->currentQueueNumber = $queue->queue_number;
            $this->currentQueueStatus = $queue->queue_status;
            $this->queueChargeSlipNo = $queue->charge_slip_no;
            $this->showQueuePanel = true;
        }
    }

    public function callForPatient(): void
    {
        if (!$this->queueId) {
            $this->warning('No queue linked to this dispensing session.');
            return;
        }

        $queue = PrescriptionQueue::find($this->queueId);
        if (!$queue || !$queue->isReady()) {
            $this->warning('Queue must be in ready status to call patient.');
            return;
        }

        DB::connection('webapp')->table('prescription_queues')
            ->where('id', $this->queueId)
            ->update(['called_at' => now()]);

        $queueService = app(PrescriptionQueueService::class);
        $queueService->logQueueAction(
            $this->queueId,
            auth()->user()->employeeid,
            'Patient called for claiming from dispensing encounter'
        );

        $queue = PrescriptionQueue::find($this->queueId);
        \App\Events\Pharmacy\QueueCalled::dispatch($queue, 'pharmacy');

        $this->success("Queue {$this->currentQueueNumber} called! Patient notified for claiming.");
    }

    public function refreshQueueStatus(): void
    {
        if (!$this->queueId) return;

        $queue = PrescriptionQueue::find($this->queueId);
        if ($queue) {
            $this->currentQueueStatus = $queue->queue_status;
            $this->currentQueueNumber = $queue->queue_number;
            $this->queueChargeSlipNo = $queue->charge_slip_no;
        }
        $this->loadQueueList();
    }

    public function toggleQueuePanel(): void
    {
        $this->showQueuePanel = !$this->showQueuePanel;
        if ($this->showQueuePanel) {
            $this->loadQueueList();
        }
    }

    public function loadQueueList(): void
    {
        $this->queueList = PrescriptionQueue::where('location_code', auth()->user()->pharm_location_id)
            ->whereDate('queued_at', today())
            ->whereIn('queue_status', ['waiting', 'preparing', 'charging', 'ready'])
            ->with(['patient'])
            ->orderByRaw("
                CASE
                    WHEN priority = 'stat' THEN 1
                    WHEN priority = 'urgent' THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('queued_at', 'asc')
            ->limit(30)
            ->get()
            ->toArray();

        $this->loadChargedQueues();
    }

    public function loadChargedQueues(): void
    {
        $this->chargedQueues = PrescriptionQueue::where('location_code', auth()->user()->pharm_location_id)
            ->whereDate('queued_at', today())
            ->whereIn('queue_status', ['charging', 'ready'])
            ->whereNotNull('enccode')
            ->with(['patient'])
            ->orderByRaw("
                CASE
                    WHEN queue_status = 'ready' THEN 1
                    WHEN queue_status = 'charging' THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('queued_at', 'asc')
            ->get()
            ->toArray();

        // Also load charged encounters not in queue (have charged items but no queue entry)
        $queueEnccodes = collect($this->chargedQueues)->pluck('enccode')->filter()->toArray();

        $excludeClause = '';
        $params = [auth()->user()->pharm_location_id, today()->toDateString()];

        if (!empty($queueEnccodes)) {
            $placeholders = implode(',', array_fill(0, count($queueEnccodes), '?'));
            $excludeClause = "AND hrxo.enccode NOT IN ($placeholders)";
            $params = array_merge($params, $queueEnccodes);
        }

        $this->chargedEncounters = DB::select("
            SELECT DISTINCT
                hrxo.enccode,
                hrxo.pcchrgcod,
                pt.patlast,
                pt.patfirst,
                pt.patmiddle,
                pt.hpercode,
                MAX(hrxo.dodate) as last_charge_date
            FROM hospital.dbo.hrxo WITH (NOLOCK)
            INNER JOIN hospital.dbo.hperson pt WITH (NOLOCK) ON hrxo.hpercode = pt.hpercode
            WHERE hrxo.loc_code = ?
                AND hrxo.estatus = 'P'
                AND hrxo.pcchrgcod IS NOT NULL
                AND CAST(hrxo.dodate AS DATE) = ?
                $excludeClause
            GROUP BY hrxo.enccode, hrxo.pcchrgcod, pt.patlast, pt.patfirst, pt.patmiddle, pt.hpercode
            ORDER BY MAX(hrxo.dodate) DESC
        ", $params);
    }

    public function queueSelectAndOpen($queueId): mixed
    {
        $queue = PrescriptionQueue::find($queueId);
        if (!$queue) {
            $this->error('Queue not found.');
            return null;
        }

        if (!$queue->enccode) {
            $this->error('No encounter linked to this queue.');
            return null;
        }

        // If queue is waiting, move to preparing
        if ($queue->isWaiting()) {
            $queueService = app(PrescriptionQueueService::class);
            $queueService->updateQueueStatus(
                $queue->id,
                'preparing',
                auth()->user()->employeeid,
                'Selected from dispensing encounter'
            );

            DB::connection('webapp')->table('prescription_queues')
                ->where('id', $queue->id)
                ->update([
                    'preparing_at' => now(),
                    'prepared_by' => auth()->user()->employeeid,
                ]);
        }

        $encrypted = Crypt::encrypt(str_replace(' ', '--', $queue->enccode));

        return redirect()->to(
            route('dispensing.view.enctr', ['enccode' => $encrypted]) . '?queue_id=' . $queue->id
        );
    }

    public function queueCallNext(): mixed
    {
        $nextQueue = PrescriptionQueue::where('location_code', auth()->user()->pharm_location_id)
            ->where('queue_status', 'waiting')
            ->whereNull('assigned_window')
            ->whereDate('queued_at', today())
            ->orderByRaw("
                CASE
                    WHEN priority = 'stat' THEN 1
                    WHEN priority = 'urgent' THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('queued_at', 'asc')
            ->first();

        if (!$nextQueue) {
            $this->warning('No waiting queues available.');
            return null;
        }

        return $this->queueSelectAndOpen($nextQueue->id);
    }

    public function queueCompleteAndNext(): mixed
    {
        // Complete current queue first
        if ($this->queueId) {
            $queue = PrescriptionQueue::find($this->queueId);
            if ($queue && !$queue->isDispensed()) {
                $queueService = app(PrescriptionQueueService::class);
                $result = $queueService->updateQueueStatus(
                    $this->queueId,
                    'dispensed',
                    auth()->user()->employeeid,
                    'Completed, moving to next queue'
                );

                if ($result['success']) {
                    DB::connection('webapp')->table('prescription_queues')
                        ->where('id', $this->queueId)
                        ->update([
                            'dispensed_by' => auth()->user()->employeeid,
                            'dispensed_at' => now(),
                        ]);
                }
            }
        }

        // Then call next
        return $this->queueCallNext();
    }

    private function autoUpdateQueueOnIssue(): void
    {
        if (!$this->queueId) return;

        $queue = PrescriptionQueue::find($this->queueId);
        if (!$queue || $queue->isDispensed() || $queue->isCancelled()) return;

        // Mark as dispensed when items are issued (from any active state)
        $queueService = app(PrescriptionQueueService::class);
        $queueService->updateQueueStatus(
            $this->queueId,
            'dispensed',
            auth()->user()->employeeid,
            'Auto-dispensed via dispensing encounter'
        );

        DB::connection('webapp')->table('prescription_queues')
            ->where('id', $this->queueId)
            ->update([
                'dispensed_by' => auth()->user()->employeeid,
                'dispensed_at' => now(),
            ]);

        $this->currentQueueStatus = 'dispensed';
    }

    public function completeQueueAndReturn(): mixed
    {
        if (!$this->queueId) {
            $this->warning('No queue linked to this dispensing session.');
            return null;
        }

        $queue = PrescriptionQueue::find($this->queueId);
        if (!$queue) {
            $this->error('Queue not found.');
            return null;
        }

        // If not already dispensed, mark as dispensed
        if (!$queue->isDispensed()) {
            $queueService = app(PrescriptionQueueService::class);
            $result = $queueService->updateQueueStatus(
                $this->queueId,
                'dispensed',
                auth()->user()->employeeid,
                'Completed from dispensing encounter'
            );

            if ($result['success']) {
                DB::connection('webapp')->table('prescription_queues')
                    ->where('id', $this->queueId)
                    ->update([
                        'dispensed_by' => auth()->user()->employeeid,
                        'dispensed_at' => now(),
                    ]);
            }
        }

        return redirect()->route('prescriptions.queue.controller2');
    }

    public function returnToQueueController(): mixed
    {
        return redirect()->route('prescriptions.queue.controller2');
    }

    // ──────────────────────────────────────────────
    // Print Prescriptions
    // ──────────────────────────────────────────────

    public function openPrintPrescriptionsModal(): void
    {
        if (!$this->hasEncounter) {
            $this->warning('No encounter loaded.');
            return;
        }

        $enccode = $this->decryptEnccode();

        $prescriptionItems = DB::connection('webapp')->select("
            SELECT
                pd.id, pd.dmdcomb, pd.dmdctr, pd.qty, pd.order_type,
                pd.remark, pd.addtl_remarks,
                pd.frequency, pd.duration, dm.drug_concat
            FROM prescription_data pd
            INNER JOIN prescription rx ON pd.presc_id = rx.id
            INNER JOIN hospital.dbo.hdmhdr dm ON pd.dmdcomb = dm.dmdcomb AND pd.dmdctr = dm.dmdctr
            WHERE rx.enccode = ? AND pd.stat = 'A'
            ORDER BY pd.created_at ASC
        ", [$enccode]);

        $this->printItems = array_map(function ($item) {
            return (array) $item;
        }, $prescriptionItems);

        // Select all by default
        $this->printSelectedItems = array_column($this->printItems, 'id');
        $this->showPrintModal = true;
    }

    public function togglePrintItemSelection($itemId): void
    {
        if (in_array($itemId, $this->printSelectedItems)) {
            $this->printSelectedItems = array_values(array_diff($this->printSelectedItems, [$itemId]));
        } else {
            $this->printSelectedItems[] = $itemId;
        }
    }

    public function selectAllPrintItems(): void
    {
        if (count($this->printSelectedItems) === count($this->printItems)) {
            $this->printSelectedItems = [];
        } else {
            $this->printSelectedItems = array_column($this->printItems, 'id');
        }
    }

    public function printPrescriptions(): void
    {
        if (empty($this->printSelectedItems)) {
            $this->warning('Please select at least one item to print.');
            return;
        }

        $enccode = $this->decryptEnccode();

        session([
            'print_encounter_enccode' => $enccode,
            'print_encounter_items' => $this->printSelectedItems,
        ]);

        $this->dispatch('open-print-window', url: url("/dispensing/prescription/print/" . urlencode($enccode)));
    }

    private function logStockIssue($stock_id, $docointkey, $dmdcomb, $dmdctr, $loc_code, $chrgcode, $exp_date, $trans_qty, $unit_price, $pcchrgamt, $user_id, $hpercode, $enccode, $toecode, $pcchrgcod, $tag, $ris, $dmdprdte, $retail_price, $concat, $stock_date, $date, $active_consumption = null, $unit_cost = 0): void
    {
        $issued_drug = DrugStockIssue::create([
            'stock_id' => $stock_id,
            'docointkey' => $docointkey,
            'dmdcomb' => $dmdcomb,
            'dmdctr' => $dmdctr,
            'loc_code' => $loc_code,
            'chrgcode' => $chrgcode,
            'exp_date' => $exp_date,
            'qty' => $trans_qty,
            'pchrgup' => $unit_price,
            'pcchrgamt' => $pcchrgamt,
            'status' => 'Issued',
            'user_id' => $user_id,
            'hpercode' => $hpercode,
            'enccode' => $enccode,
            'toecode' => $toecode,
            'pcchrgcod' => $pcchrgcod,
            'ems' => $tag == 'ems' ? $trans_qty : false,
            'maip' => $tag == 'maip' ? $trans_qty : false,
            'wholesale' => $tag == 'wholesale' ? $trans_qty : false,
            'pay' => $tag == 'pay' ? $trans_qty : false,
            'opdpay' => $tag == 'opdpay' ? $trans_qty : false,
            'service' => $tag == 'service' ? $trans_qty : false,
            'caf' => $tag == 'caf' ? $trans_qty : false,
            'ris' => $ris ? true : false,
            'konsulta' => $tag == 'konsulta' ? $trans_qty : false,
            'pcso' => $tag == 'pcso' ? $trans_qty : false,
            'phic' => $tag == 'phic' ? $trans_qty : false,
            'doh_free' => $tag == 'doh_free' ? $trans_qty : false,
            'dmdprdte' => $dmdprdte,
        ]);

        $date = Carbon::parse($date)->startOfMonth()->format('Y-m-d');

        $log = DrugStockLog::firstOrNew([
            'loc_code' => $loc_code,
            'dmdcomb' => $dmdcomb,
            'dmdctr' => $dmdctr,
            'chrgcode' => $chrgcode,
            'unit_cost' => $unit_cost,
            'unit_price' => $retail_price,
            'consumption_id' => $active_consumption,
        ]);
        $log->issue_qty += $trans_qty;
        $log->wholesale += $issued_drug->wholesale;
        $log->ems += $issued_drug->ems;
        $log->maip += $issued_drug->maip;
        $log->caf += $issued_drug->caf;
        $log->ris += $issued_drug->ris ? 1 : 0;
        $log->pay += $issued_drug->pay;
        $log->service += $issued_drug->service;
        $log->konsulta += $issued_drug->konsulta;
        $log->pcso += $issued_drug->pcso;
        $log->phic += $issued_drug->phic;
        $log->opdpay += $issued_drug->opdpay;
        $log->doh_free += $issued_drug->doh_free;
        $log->save();

        $card = DrugStockCard::firstOrNew([
            'chrgcode' => $chrgcode,
            'loc_code' => $loc_code,
            'dmdcomb' => $dmdcomb,
            'dmdctr' => $dmdctr,
            'exp_date' => $exp_date,
            'stock_date' => $stock_date,
            'drug_concat' => $concat,
            'dmdprdte' => $dmdprdte,
        ]);
        $card->iss += $trans_qty;
        $card->bal -= $trans_qty;
        $card->save();
    }
}
