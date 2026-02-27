<?php

namespace App\Http\Livewire\Pharmacy\Dispensing;

use Carbon\Carbon;
use Livewire\Component;
use App\Models\Pharmacy\Drug;
use App\Jobs\LogDrugStockIssue;
use Illuminate\Support\Facades\DB;
use App\Models\Hospital\Department;
use App\Models\References\ChargeCode;
use Illuminate\Support\Facades\Crypt;
use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\Pharmacy\Drugs\DrugStockLog;
use App\Models\Pharmacy\Drugs\DrugStockCard;
use App\Models\Pharmacy\Dispensing\DrugOrder;
use App\Models\Pharmacy\Drugs\DrugStockIssue;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use App\Models\Record\Encounters\EncounterLog;
use App\Models\Record\Prescriptions\Prescription;
use App\Models\Pharmacy\Dispensing\DrugOrderReturn;
use App\Models\Pharmacy\Dispensing\OrderChargeCode;
use App\Models\Record\Prescriptions\PrescriptionData;
use App\Models\Record\Prescriptions\PrescriptionDataIssued;

class EncounterTransactionView extends Component
{
    use LivewireAlert;

    protected $listeners = ['charge_items', 'issue_order', 'add_item', 'return_issued', 'add_prescribed_item', 'delete_item', 'deactivate_rx', 'update_qty'];

    public $generic, $charge_code = [];
    public $enccode, $location_id, $hpercode, $toecode, $mssikey;

    public $order_qty, $unit_price, $return_qty, $docointkey;
    public $item_id;
    public $ems, $maip, $wholesale, $caf, $type, $konsulta, $pcso, $phic, $pay, $service, $doh_free, $bnb = false;

    public $is_ris = false;
    public $remarks;

    public $charges;
    protected $encounter = [];

    public $selected_items = [];
    public $marked_items = false;
    public $selected_remarks, $new_remarks;

    public $patient;
    public $active_prescription = [], $extra_prescriptions = [];
    public $active_prescription_all = [], $extra_prescriptions_all = [];
    public $adm;
    public $rx_charge_code;

    public $patient_room, $wardname, $rmname;
    public $code;
    public $encdate;
    public $diagtext;
    public $patlast;
    public $patfirst;
    public $patmiddle, $billstat = null;
    public $adttl_remarks;

    public $rx_id, $rx_dmdcomb, $rx_dmdctr, $empid, $mss, $deptcode;

    public $stock_changes = false;


    public function render()
    {
        $enccode = str_replace('--', ' ', Crypt::decrypt($this->enccode));

        if ($this->toecode == 'WALKN') {
            $rxos = DB::select("SELECT docointkey, pcchrgcod, dodate, pchrgqty, estatus, qtyissued, pchrgup, pcchrgamt, drug_concat, chrgdesc, remarks, mssikey, tx_type, prescription_data_id
                                    FROM henctr enctr
                                    INNER JOIN hospital2.dbo.hrxo ON enctr.enccode = hrxo.enccode
                                    INNER JOIN hdmhdr ON hdmhdr.dmdcomb = hrxo.dmdcomb AND hdmhdr.dmdctr = hrxo.dmdctr
                                    INNER JOIN hcharge ON orderfrom = chrgcode
                                    LEFT JOIN hpatmss ON hrxo.enccode = hpatmss.enccode
                                    WHERE hrxo.hpercode = '" . $this->hpercode . "' AND enctr.toecode = 'WALKN'
                                    ORDER BY dodate DESC");
        } else {
            $rxos = DB::select("SELECT docointkey, pcchrgcod, dodate, pchrgqty, estatus, qtyissued, pchrgup, pcchrgamt, drug_concat, chrgdesc, remarks, mssikey, tx_type, prescription_data_id
                                    FROM hospital2.dbo.hrxo
                                    INNER JOIN hdmhdr ON hdmhdr.dmdcomb = hrxo.dmdcomb AND hdmhdr.dmdctr = hrxo.dmdctr
                                    INNER JOIN hcharge ON orderfrom = chrgcode
                                    LEFT JOIN hpatmss ON hrxo.enccode = hpatmss.enccode
                                    WHERE hrxo.enccode = '" . $enccode . "'
                                    ORDER BY dodate DESC");
        }

        $stocks = DB::select("SELECT pharm_drug_stocks.dmdcomb, pharm_drug_stocks.dmdctr, pharm_drug_stocks.drug_concat, hcharge.chrgdesc, pharm_drug_stocks.chrgcode, hdmhdrprice.retail_price, dmselprice, pharm_drug_stocks.loc_code, pharm_drug_stocks.dmdprdte as dmdprdte, SUM(stock_bal) as stock_bal, MAX(id) as id, MIN(exp_date) as exp_date, hdmhdrprice.acquisition_cost
                                FROM hospital2.dbo.pharm_drug_stocks
                                INNER JOIN hcharge on hcharge.chrgcode = pharm_drug_stocks.chrgcode
                                INNER JOIN hdmhdrprice on hdmhdrprice.dmdprdte = pharm_drug_stocks.dmdprdte
                                WHERE loc_code = '" . $this->location_id . "'
                                AND pharm_drug_stocks.drug_concat LIKE '%" . implode("''", explode("'", $this->generic)) . "%'
                                AND stock_bal > 0
                                GROUP BY pharm_drug_stocks.dmdcomb, pharm_drug_stocks.dmdctr, pharm_drug_stocks.chrgcode, hdmhdrprice.retail_price, hdmhdrprice.acquisition_cost, dmselprice, pharm_drug_stocks.drug_concat, hcharge.chrgdesc, pharm_drug_stocks.loc_code, pharm_drug_stocks.dmdprdte
                                ORDER BY pharm_drug_stocks.drug_concat");


        $summaries = DB::select("
            SELECT drug_concat, SUM(qtyissued) qty_issued, MAX(dodtepost) last_issue
                FROM hrxo
            JOIN hdmhdr ON hrxo.dmdcomb = hdmhdr.dmdcomb AND hrxo.dmdctr = hdmhdr.dmdctr
                WHERE enccode = '" . $enccode . "' AND estatus = 'S'
            GROUP BY drug_concat
        ");

        $departments = DB::select("SELECT * FROM hdept WHERE deptstat = 'A'");

        $this->dispatchBrowserEvent('issued');
        $encounter = $this->encounter;

        return view('livewire.pharmacy.dispensing.encounter-transaction-view', compact(
            'rxos',
            'stocks',
            'encounter',
            'departments',
            'summaries',
        ));
    }


    public function mount($enccode)
    {
        $this->enccode = $enccode;

        $this->location_id = session('pharm_location_id');

        $enccode = str_replace('--', ' ', Crypt::decrypt($this->enccode));

        $encounter = collect(DB::select("SELECT TOP 1 enctr.hpercode, enctr.toecode, enctr.enccode, enctr.encdate, diag.diagtext, pat.patlast, pat.patfirst, pat.patmiddle,
                                                mss.mssikey, ward.wardname, room.rmname, track.billstat
                                FROM henctr as enctr
                                LEFT JOIN hactrack as track ON enctr.enccode = track.enccode
                                LEFT JOIN hencdiag as diag ON enctr.enccode = diag.enccode
                                INNER JOIN hperson as pat ON enctr.hpercode = pat.hpercode
                                LEFT JOIN hpatmss as mss ON enctr.enccode = mss.enccode
                                LEFT JOIN hpatroom as patroom ON enctr.enccode = patroom.enccode
                                LEFT JOIN hward as ward ON patroom.wardcode = ward.wardcode
                                LEFT JOIN hroom as room ON patroom.rmintkey = room.rmintkey
                                WHERE enctr.enccode = '" . $enccode . "'
                                ORDER BY patroom.hprdate DESC
                                "))->first();


        $this->active_prescription = Prescription::where('enccode', $enccode)->has('data_active')->get();
        $this->active_prescription_all = Prescription::where('enccode', $enccode)->with('data')->get();
        $past_log = null;
        switch ($encounter->toecode) {
            case 'ADM':
                $past_log = EncounterLog::where('hpercode', $encounter->hpercode)
                    ->where(function ($query) {
                        $query->where('toecode', 'ERADM')
                            ->orWhere('toecode', 'OPDAD');
                    })
                    ->latest('encdate')
                    ->first();
                break;

            case 'OPDAD':
                $past_log = EncounterLog::where('hpercode', $encounter->hpercode)
                    ->where(function ($query) {
                        $query->where('toecode', 'OPD');
                    })
                    ->latest('encdate')
                    ->first();
                break;

            case 'ERADM':
                $past_log = EncounterLog::where('hpercode', $encounter->hpercode)
                    ->where(function ($query) {
                        $query->where('toecode', 'ER');
                    })
                    ->latest('encdate')
                    ->first();
                break;
        }

        if ($past_log) {
            $this->extra_prescriptions = Prescription::where('enccode', $past_log->enccode)->with('data_active')->has('data_active')->get();
            $this->extra_prescriptions_all = Prescription::where('enccode', $past_log->enccode)->with('data')->get();
        }

        if (!$this->hpercode) {
            $this->hpercode = $encounter->hpercode;
            $this->toecode = $encounter->toecode;
        }
        $this->mssikey = $encounter->mssikey;
        $this->encounter = $encounter;
        $this->code  = $encounter->enccode;
        $this->encdate = $encounter->encdate;
        $this->diagtext = $encounter->diagtext;
        $this->patlast = $encounter->patlast;
        $this->patfirst = $encounter->patfirst;
        $this->patmiddle = $encounter->patmiddle;
        $this->wardname = $encounter->wardname;
        $this->rmname = $encounter->rmname;
        $this->billstat = $encounter->billstat;
        if (!$this->charges) {
            $this->charges = ChargeCode::where('bentypcod', 'DRUME')
                ->where('chrgstat', 'A')
                ->whereIn('chrgcode', app('chargetable'))
                ->get();
        }
        // $this->departments = Department::where('deptstat', 'A')->get();
    }

    public function charge_items()
    {
        $charge_code = OrderChargeCode::create([
            'charge_desc' => 'a',
        ]);
        $cnt = 0;
        $pcchrgcod = 'P' . date('y') . '-' . sprintf('%07d', $charge_code->id);
        foreach ($this->selected_items as $docointkey) {
            $cnt = DB::update(
                "UPDATE hospital2.dbo.hrxo SET pcchrgcod = '" . $pcchrgcod . "', estatus = 'P' WHERE docointkey = " . $docointkey . " AND ((estatus = 'U' OR orderfrom = 'DRUMK' OR pchrgup = 0) AND pcchrgcod IS NULL)"
            );
        }
        if ($cnt and $cnt != 0) {
            $cnt = 0;
            $this->dispatchBrowserEvent('charged', ['pcchrgcod' => $pcchrgcod]);
        } else {
            $this->alert('error', 'No item to charge.');
        }
    }

    public function issue_order()
    {

        $enccode = str_replace('--', ' ', Crypt::decrypt($this->enccode));
        $cnt = 0;

        $selected_items = implode(',', $this->selected_items);
        $rxos = collect(DB::select("SELECT * FROM hrxo WHERE docointkey IN (" . $selected_items . ") AND (estatus = 'P' OR orderfrom = 'DRUMK' OR pchrgup = 0)"))->all();
        if ($this->toecode == 'ADM' or $this->toecode == 'OPDAD' or $this->toecode == 'ERADM') {
            switch ($this->mssikey) {
                case 'MSSA11111999':
                case 'MSSB11111999':
                    $this->type = 'pay';
                    break;

                case 'MSSC111111999':
                    $this->type = 'pay';
                    $class = 'PP1';
                    break;

                case 'MSSC211111999':
                    $class = 'PP2';
                    break;

                case 'MSSC311111999':
                    $class = 'PP3';
                    break;

                case 'MSSD11111999':
                    $this->type = 'service';
                    break;

                default:
                    $this->type = 'service';
            }
            if ($this->bnb) {
                $this->type = 'pay';
            } else {
                $this->type = 'service';
            }
        } else {
            if ($this->ems) {
                $this->type = 'ems';
            } else if ($this->maip) {
                $this->type = 'maip';
            } else if ($this->wholesale) {
                $this->type = 'wholesale';
            } else if ($this->service) {
                $this->type = 'service';
            } else if ($this->caf) {
                $this->type = 'caf';
            } else if ($this->is_ris) {
                $this->type = 'ris';
            } else if ($this->pcso) {
                $this->type = 'pcso';
            } else if ($this->phic) {
                $this->type = 'phic';
            } else if ($this->konsulta) {
                $this->type = 'konsulta';
            } else if ($this->doh_free) {
                $this->type = 'doh_free';
            } else {
                $this->type = 'opdpay';
            }
            if ($this->toecode != 'ER') {
                $this->validate(['deptcode' => 'required'], ['deptcode.required' => 'Please select department.']);
            }
        }
        $temp_type = $this->type;

        foreach ($rxos as $rxo) {

            if ($rxo->orderfrom == 'DRUMK') {
                $this->type = 'service';
            }
            $this->type = $temp_type;

            $stocks = DB::select(
                "SELECT pharm_drug_stocks.*, hdmhdrprice.dmduprice
                    FROM pharm_drug_stocks
                    JOIN hdmhdrprice ON pharm_drug_stocks.dmdprdte = hdmhdrprice.dmdprdte
                WHERE pharm_drug_stocks.dmdcomb = '" . $rxo->dmdcomb . "'
                    AND pharm_drug_stocks.dmdctr = '" . $rxo->dmdctr . "'
                    AND pharm_drug_stocks.chrgcode = '" . $rxo->orderfrom . "'
                    AND pharm_drug_stocks.loc_code = '" . session('pharm_location_id') . "'
                    AND pharm_drug_stocks.exp_date > '" . date('Y-m-d') . "'
                    AND pharm_drug_stocks.stock_bal > 0
                ORDER BY pharm_drug_stocks.exp_date ASC"
            );
            if ($stocks) {
                $total_deduct = $rxo->pchrgqty;
                $dmdcomb = $rxo->dmdcomb;
                $dmdctr = $rxo->dmdctr;
                $docointkey = $rxo->docointkey;
                $loc_code = $rxo->loc_code;
                $chrgcode = $rxo->orderfrom;
                $unit_price = $rxo->pchrgup;
                $pcchrgamt = $rxo->pcchrgamt;
                $pcchrgcod = $rxo->pcchrgcod;
                $tag = $this->type;

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
                                "UPDATE hospital2.dbo.pharm_drug_stocks SET stock_bal = '" . $stock_bal . "' WHERE id = '" . $stock->id . "'"
                            );
                        } else {
                            $total_deduct = 0;
                        }
                        $drug_concat = '';
                        $drug_concat = implode("", explode('_', $stock->drug_concat));

                        $this->log_stock_issue($stock->id, $docointkey, $dmdcomb, $dmdctr, $loc_code, $chrgcode, $stock->exp_date, $trans_qty, $unit_price, $pcchrgamt, session('user_id'), $rxo->hpercode, $rxo->enccode, $this->toecode, $pcchrgcod, $tag, $rxo->ris, $stock->dmdprdte, $stock->retail_price, $drug_concat, date('Y-m-d'), now(), session('active_consumption'), $stock->dmduprice);
                    }
                }
                if ($cnt == 1) {
                    $cnt = DB::update(
                        "UPDATE hospital2.dbo.hrxo SET estatus = 'S', qtyissued = '" . $rxo->pchrgqty . "', tx_type = '" . $this->type . "', dodtepost = '" . now() . "', dotmepost = '" . now() . "', deptcode = '" . $this->deptcode . "' WHERE docointkey = '" . $rxo->docointkey . "' AND (estatus = 'P' OR orderfrom = 'DRUMK' OR pchrgup = 0)"
                    );
                    $this->log_hrxoissue($rxo->docointkey, $rxo->enccode, $rxo->hpercode, $rxo->dmdcomb, $rxo->dmdctr, $rxo->pchrgqty, session('employeeid'), $rxo->orderfrom, $rxo->pcchrgcod, $rxo->pchrgup, $rxo->ris, $rxo->prescription_data_id, now(), $rxo->dmdprdte);
                }
            } else {
                $insuf = Drug::select('drug_concat')->where('dmdcomb', $rxo->dmdcomb)->where('dmdctr', $rxo->dmdctr)->first();
                return $this->alert('error', 'Insufficient Stock Balance. ' . $insuf->drug_concat);
            }
        }

        if ($cnt == 1) {
            $this->alert('success', 'Order issued successfully.');
        } else {
            $this->alert('error', 'No item to issue.');
        }
    }

    public function log_hrxoissue($docointkey, $enccode, $hpercode, $dmdcomb, $dmdctr, $pchrgqty, $employeeid, $orderfrom, $pcchrgcod, $pchrgup, $ris, $prescription_data_id, $date, $dmdprdte)
    {
        if ($prescription_data_id) {
            PrescriptionDataIssued::create([
                'presc_data_id' => $prescription_data_id,
                'docointkey' => $docointkey,
                'qtyissued' => $pchrgqty,
            ]);
        } else {
            $rx_header = Prescription::where('enccode', $enccode)
                ->with('data_active')
                ->get();
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
                            "UPDATE hospital2.dbo.hrxo SET prescription_data_id = ?, prescribed_by = ? WHERE docointkey = ?",
                            [$rx_data->id, $rx_data->entry_by, $docointkey]
                        );

                        $rx_data->stat = 'I';
                        $rx_data->save();
                    }
                }
            }
        }

        // DrugOrderIssue::updateOrCreate([
        //     'docointkey' => $docointkey,
        //     'enccode' => $enccode,
        //     'hpercode' => $hpercode,
        //     'dmdcomb' => $dmdcomb,
        //     'dmdctr' => $dmdctr,
        // ], [
        //     'issuedte' => $date,
        //     'issuetme' => $date,
        //     'qty' => $pchrgqty,
        //     'issuedby' => $employeeid,
        //     'status' => 'A', //A
        //     'rxolock' => 'N', //N
        //     'updsw' => 'N', //N
        //     'confdl' => 'N', //N
        //     'entryby' => $employeeid,
        //     'locacode' => 'PHARM', //PHARM
        //     'dmdprdte' => $dmdprdte,
        //     'issuedfrom' => $orderfrom,
        //     'pcchrgcod' => $pcchrgcod,
        //     'chrgcode' => $orderfrom,
        //     'pchrgup' => $pchrgup,
        //     'issuetype' => 'c', //c
        //     'ris' =>  $ris ? true : false,
        // ]);
    }

    public function log_stock_issue($stock_id, $docointkey, $dmdcomb, $dmdctr, $loc_code, $chrgcode, $exp_date, $trans_qty, $unit_price, $pcchrgamt, $user_id, $hpercode, $enccode, $toecode, $pcchrgcod, $tag, $ris, $dmdprdte, $retail_price, $concat, $stock_date, $date, $active_consumption = null, $unit_cost)
    {
        $issued_drug = DrugStockIssue::create([
            'stock_id' => $stock_id,
            'docointkey' => $docointkey,
            'dmdcomb' => $dmdcomb,
            'dmdctr' => $dmdctr,
            'loc_code' => $loc_code,
            'chrgcode' => $chrgcode,
            'exp_date' => $exp_date,
            'qty' =>  $trans_qty,
            'pchrgup' =>  $unit_price,
            'pcchrgamt' =>  $pcchrgamt,
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
            'ris' =>  $ris ? true : false,

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

        // added columns
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
        return;
    }

    public function add_item($dmdcomb, $dmdctr, $chrgcode, $loc_code, $dmdprdte, $id, $available, $exp_date)
    {
        $with_rx = false;
        if ($dmdcomb == $this->rx_dmdcomb and $dmdctr == $this->rx_dmdctr) {
            $with_rx = true;
            $rx_id = $this->rx_id;
            $empid = $this->empid;
        }

        $total_deduct = $this->order_qty;

        if ($this->toecode == 'ADM' or $this->toecode == 'OPDAD' or $this->toecode == 'ERADM') {
            switch ($this->mssikey) {
                case 'MSSA11111999':
                case 'MSSB11111999':
                    $this->type = 'pay';
                    break;

                case 'MSSC111111999':
                    $this->type = 'pay';
                    $class = 'PP1';
                    break;

                case 'MSSC211111999':
                    $class = 'PP2';
                    break;

                case 'MSSC311111999':
                    $class = 'PP3';
                    break;

                case 'MSSD11111999':
                    $this->type = 'service';
                    break;

                default:
                    $this->type = 'service';
            }
            if ($this->bnb) {
                $this->type = 'pay';
            } else {
                $this->type = 'service';
            }
        } else {
            if ($this->ems) {
                $this->type = 'ems';
            } else if ($this->maip) {
                $this->type = 'maip';
            } else if ($this->wholesale) {
                $this->type = 'wholesale';
            } else if ($this->service) {
                $this->type = 'service';
            } else if ($this->caf) {
                $this->type = 'caf';
            } else if ($this->is_ris) {
                $this->type = 'ris';
            } else if ($this->pcso) {
                $this->type = 'pcso';
            } else if ($this->phic) {
                $this->type = 'phic';
            } else if ($this->konsulta) {
                $this->type = 'konsulta';
            } else if ($this->doh_free) {
                $this->type = 'doh_free';
            } else {
                $this->type = 'opdpay';
            }
        }

        if ($this->is_ris or $available >= $total_deduct) {
            $enccode = str_replace('--', ' ', Crypt::decrypt($this->enccode));
            $docointkey = '0000040' . $this->hpercode . date('m/d/Yh:i:s', strtotime(now())) . $chrgcode . $dmdcomb . $dmdctr;

            DB::insert("INSERT INTO hospital2.dbo.hrxo(docointkey, enccode, hpercode, rxooccid, rxoref, dmdcomb, repdayno1, rxostatus,
                            rxolock, rxoupsw, rxoconfd, dmdctr, estatus, entryby, ordcon, orderupd, locacode, orderfrom, issuetype,
                            has_tag, tx_type, ris, pchrgqty, pchrgup, pcchrgamt, dodate, dotime, dodtepost, dotmepost, dmdprdte, exp_date, loc_code, item_id, remarks, prescription_data_id, prescribed_by )
                        VALUES ( '" . $docointkey . "', '" . $enccode . "', '" . $this->hpercode . "', '1', '1', '" . $dmdcomb . "', '1', 'A',
                            'N', 'N', 'N', '" . $dmdctr . "', 'U', '" . session('employeeid') . "', 'NEWOR', 'ACTIV', 'PHARM', '" . $chrgcode . "', 'c',
                            '" . ($this->type ? true : false) . "', '" . $this->type . "', '" . ($this->is_ris ? true : false) . "', '" . $this->order_qty . "', '" . $this->unit_price . "',
                            '" . $this->order_qty * $this->unit_price . "', '" . now() . "', '" . now() . "', '" . now() . "', '" . now() . "', '" . $dmdprdte . "', '" . $exp_date . "',
                            '" . $loc_code . "', '" . $id . "', '" . ($this->remarks ?? '') . "', '" . ($with_rx ? $rx_id : null) . "', '" . ($with_rx ? $empid : null) . "' )");

            if ($with_rx) {
                DB::connection('webapp')->table('webapp.dbo.prescription_data')
                    ->where('id', $rx_id)
                    ->update(['stat' => 'I']);
            }

            $this->resetExcept('code', 'generic', 'rx_dmdcomb', 'rx_dmdctr', 'rx_id', 'empid', 'stocks', 'enccode', 'location_id', 'encounter', 'charges', 'hpercode', 'toecode', 'selected_items', 'patient', 'active_prescription', 'adm', 'wardname', 'rmname', 'mss', 'summaries');
            // $this->emit('refresh');
            $this->alert('success', 'Item added.');
            // return redirect(route('dispensing.view.enctr', $this->enccode));
        } else {
            $this->alert('error', 'Insufficient stock!');
        }
    }

    public function delete_item()
    {
        $selectedItems = implode(',', array_map(function ($item) {
            return $item;
        }, $this->selected_items));
        DB::delete("DELETE FROM hrxo WHERE docointkey IN(" . $selectedItems . ") AND (estatus = 'U' OR pcchrgcod IS NULL)");

        $this->reset('selected_items');

        $this->emit('refresh');
        $this->alert('success', 'Selected item/s deleted!');
    }

    public function return_issued(DrugOrder $item)
    {
        $this->validate([
            'return_qty' => ['required', 'numeric', 'min:1', 'max:' . $item->qtyissued],
            'unit_price' => 'required',
            'docointkey' => 'required',
        ]);
        // $issued_items = DrugStockIssue::where('docointkey', $this->docointkey)->latest()->with('stock')->get();
        $isReturned = DrugOrderReturn::where('docointkey', $this->docointkey)
            ->count();

        //RECORD RETURN ITEM TO hrxoreturn table
        if (!$isReturned) {
            DB::insert("INSERT INTO hospital2.dbo.hrxoreturn(
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
            '" . session('employeeid') . "',
            'A',
            'N',
            'N',
            'N',
            '" . session('employeeid') . "',
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

            //DEDUCT QTYISSUED FROM hrxo and DrugStockIssue table
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
                //Return QTY to DrugStock table
                $stock = DrugStock::firstOrCreate([
                    'dmdcomb' => $item->dmdcomb,
                    'dmdctr' => $item->dmdctr,
                    'loc_code' =>  $this->location_id,
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
                    'consumption_id' => session('active_consumption'),
                ]);
                $log->return_qty += $returned_qty;


                $drug_concat = '';
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

            $this->emit('refresh');
            $this->alert('success', 'Item returned.');
        } else {
            $this->alert('error', 'Item already returned.');
        }
    }

    public function add_prescribed_item($dmdcomb, $dmdctr)
    {
        $rx_id = $this->rx_id;
        $empid = $this->empid;
        if ($this->ems) {
            $this->type = 'ems';
        } else if ($this->maip) {
            $this->type = 'maip';
        } else if ($this->wholesale) {
            $this->type = 'wholesale';
        } else if ($this->service) {
            $this->type = 'service';
        } else if ($this->caf) {
            $this->type = 'caf';
        } else if ($this->is_ris) {
            $this->type = 'ris';
        } else if ($this->pcso) {
            $this->type = 'pcso';
        } else if ($this->phic) {
            $this->type = 'phic';
        } else if ($this->konsulta) {
            $this->type = 'konsulta';
        } else {
            $this->type = 'pay';
        }

        $dm = DrugStock::where('dmdcomb', $dmdcomb)
            ->where('dmdctr', $dmdctr)
            ->where('chrgcode', $this->rx_charge_code)
            ->where('loc_code', $this->location_id)
            ->where('stock_bal', '>', '0')
            ->orderBy('exp_date', 'ASC')
            ->first();

        if ($dm) {
            $enccode = str_replace('--', ' ', Crypt::decrypt($this->enccode));

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
                'entryby' => session('employeeid'),
                'ordcon' => 'NEWOR',
                'orderupd' => 'ACTIV',
                'locacode' => 'PHARM',
                'orderfrom' => $dm->chrgcode,
                'issuetype' => 'c',
                'has_tag' => $this->type ? true : false, //added
                'tx_type' => $this->type, //added
                'ris' => $this->is_ris ? true : false,
                'pchrgqty' => $this->order_qty,
                'pchrgup' => $dm->current_price->dmselprice,
                'pcchrgamt' => $this->order_qty * $dm->current_price->dmselprice,
                'dodate' => now(),
                'dotime' => now(),
                'dodtepost' => now(),
                'dotmepost' => now(),
                'dmdprdte' => $dm->dmdprdte,
                'exp_date' => $dm->exp_date, //added
                'loc_code' => $dm->loc_code, //added
                'item_id' => $dm->id, //added
                'remarks' => $this->remarks, //added
                'prescription_data_id' => $rx_id,
                'prescribed_by' => $empid,
            ]);
            DB::connection('webapp')->table('webapp.dbo.prescription_data')
                ->where('id', $rx_id)
                ->update(['stat' => 'I']);

            $this->resetExcept('generic', 'stocks', 'enccode', 'location_id', 'encounter', 'charges', 'hpercode', 'toecode', 'selected_items', 'patient', 'active_prescription', 'adm', 'wardname', 'rmname', 'summaries');
            $this->emit('refresh');
            $this->alert('success', 'Item added.');
        } else {
            $this->alert('error', 'Insufficient stock!');
        }
    }

    public function update_remarks()
    {
        $this->validate(['selected_remarks' => ['required'], 'new_remarks' => ['nullable', 'string', 'max:255']]);
        $rxo = DrugOrder::find($this->selected_remarks);
        $rxo->remarks = $this->new_remarks;
        $rxo->save();
        $this->alert('success', 'Remarks updated');
        return redirect(route('dispensing.view.enctr', $this->enccode));
    }

    public function deactivate_rx($rx_id)
    {
        $data = PrescriptionData::find($rx_id);
        $data->stat = 'I';
        $data->addtl_remarks = $this->adttl_remarks;
        $data->save();
        $this->alert('success', 'Prescription updated!');
    }

    public function update_qty($docointkey)
    {
        $this->validate([
            'order_qty' => ['required', 'numeric', 'min:1'],
        ]);
        DrugOrder::where('docointkey', $docointkey)
            ->update([
                'pchrgqty' => $this->order_qty,
                'pchrgup' => $this->unit_price,
                'pcchrgamt' =>  $this->order_qty * $this->unit_price
            ]);
        $this->alert('success', 'Order updated!');
    }
}
