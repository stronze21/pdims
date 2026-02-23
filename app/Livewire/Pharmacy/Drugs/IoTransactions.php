<?php

namespace App\Livewire\Pharmacy\Drugs;

use App\Models\Pharmacy\Drug;
use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\Pharmacy\Drugs\DrugStockCard;
use App\Models\Pharmacy\Drugs\DrugStockLog;
use App\Models\Pharmacy\Drugs\InOutTransaction;
use App\Models\Pharmacy\Drugs\InOutTransactionItem;
use App\Models\Pharmacy\PharmLocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class IoTransactions extends Component
{
    use WithPagination;
    use Toast;

    // View mode: 'issuer' (warehouse) or 'requestor' (location)
    public $view_mode = 'requestor';

    public $stock_id;
    public $requested_qty;
    public $remarks = '';
    public $location_id;
    public $selected_request;
    public $chrgcode = '';
    public $issue_qty = 0;
    public $search = '';
    public $available_drugs = [];
    public $issueModal = false;
    public $requestModal = false;
    public $issuing_location_id = '';
    public $requesting_location_id = '';

    public function mount()
    {
        $this->requesting_location_id = auth()->user()->pharm_location_id;
        $this->issuing_location_id = '';
    }

    public function render()
    {
        $trans = InOutTransaction::with(['location', 'from_location', 'drug', 'charge'])
            ->when($this->issuing_location_id, function ($query) {
                $query->where('request_from', $this->issuing_location_id);
            })
            ->when($this->requesting_location_id, function ($query) {
                $query->where('loc_code', $this->requesting_location_id);
            })
            ->when($this->search, function ($query, $search) {
                return $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('trans_no', 'like', "%{$search}%")
                        ->orWhereHas('drug', function ($drugQuery) use ($search) {
                            $drugQuery->where('drug_concat', 'like', "%{$search}%");
                        });
                });
            })
            ->latest();

        $locations = PharmLocation::all();

        $drugs = Drug::where('dmdstat', 'A')
            ->whereNotNull('drug_concat')
            ->has('stocks')
            ->has('generic')->orderBy('drug_concat', 'ASC')
            ->get();

        return view('livewire.pharmacy.drugs.io-transactions', [
            'trans' => $trans->paginate(20),
            'locations' => $locations,
            'drugs' => $drugs,
        ]);
    }

    public function switchMode($mode)
    {
        $this->view_mode = $mode;
    }

    public function addRequest()
    {
        $dm = explode(',', $this->stock_id);
        $dmdcomb = $dm[0];
        $dmdctr = $dm[1];

        $this->validate([
            'requested_qty' => ['required', 'numeric', 'min:1'],
            'remarks' => ['nullable', 'string'],
        ]);

        $reference_no = Carbon::now()->format('y-m-') . (sprintf("%04d", count(InOutTransaction::select(DB::raw('COUNT(trans_no)'))->groupBy('trans_no')->get()) + 1));

        InOutTransaction::create([
            'trans_no' => $reference_no,
            'dmdcomb' => $dmdcomb,
            'dmdctr' => $dmdctr,
            'requested_qty' => $this->requested_qty,
            'requested_by' => auth()->id(),
            'loc_code' => auth()->user()->pharm_location_id,
            'request_from' => $this->location_id,
            'remarks_request' => $this->remarks,
        ]);

        $this->reset('stock_id', 'requested_qty', 'remarks', 'location_id');
        $this->requestModal = false;
        $this->success('Request added!');
    }

    public function addMoreRequest()
    {
        $dm = explode(',', $this->stock_id);
        $dmdcomb = $dm[0];
        $dmdctr = $dm[1];

        $past = InOutTransaction::where('loc_code', auth()->user()->pharm_location_id)->latest()->first();

        $this->validate([
            'remarks' => ['nullable', 'string'],
        ]);

        if (!$past) {
            $this->error('No previous request found!');
            return;
        }

        $reference_no = $past->trans_no;

        InOutTransaction::create([
            'trans_no' => $reference_no,
            'dmdcomb' => $dmdcomb,
            'dmdctr' => $dmdctr,
            'requested_qty' => $this->requested_qty,
            'requested_by' => auth()->id(),
            'loc_code' => auth()->user()->pharm_location_id,
            'request_from' => $past->request_from,
            'remarks_request' => $this->remarks,
        ]);

        $this->reset('stock_id', 'requested_qty', 'remarks');
        $this->requestModal = false;
        $this->success('Request added!');
    }

    public function selectRequest(InOutTransaction $txn)
    {
        $this->selected_request = $txn;
        $this->issue_qty = $txn->requested_qty;
        $this->available_drugs = DB::select("
                SELECT charge.chrgcode, charge.chrgdesc, SUM(pdsl.stock_bal) avail FROM pharm_drug_stocks pdsl
                INNER JOIN hcharge charge ON pdsl.chrgcode = charge.chrgcode
                WHERE pdsl.dmdcomb = ?
                    AND pdsl.dmdctr = ?
                    AND pdsl.loc_code = ?
                    AND pdsl.stock_bal > 0
                    AND pdsl.exp_date > ?
                GROUP BY charge.chrgcode, charge.chrgdesc
            ", [$txn->dmdcomb, $txn->dmdctr, $txn->request_from, now()]);
        $this->issueModal = true;
    }

    public function issueRequest()
    {
        $this->validate([
            'issue_qty' => ['required', 'numeric', 'min:1'],
            'chrgcode' => ['required'],
            'selected_request' => ['required'],
            'remarks' => ['nullable', 'string', 'max:255']
        ]);

        $issue_qty = $this->issue_qty;
        $issued_qty = 0;
        $location_id = PharmLocation::find($this->selected_request->request_from)->id;

        $available_qty = DrugStock::where('dmdcomb', $this->selected_request->dmdcomb)
            ->where('dmdctr', $this->selected_request->dmdctr)
            ->where('chrgcode', $this->chrgcode)
            ->where('exp_date', '>', date('Y-m-d'))
            ->where('loc_code', $location_id)
            ->where('stock_bal', '>', '0')
            ->groupBy('chrgcode')
            ->sum('stock_bal');

        if ($available_qty >= $issue_qty) {
            $stocks = DrugStock::where('dmdcomb', $this->selected_request->dmdcomb)
                ->where('dmdctr', $this->selected_request->dmdctr)
                ->where('chrgcode', $this->chrgcode)
                ->where('exp_date', '>', date('Y-m-d'))
                ->where('loc_code', $location_id)
                ->where('stock_bal', '>', '0')
                ->oldest('exp_date')
                ->get();

            foreach ($stocks as $stock) {
                if ($issue_qty) {
                    if ($issue_qty > $stock->stock_bal) {
                        $trans_qty = $stock->stock_bal;
                        $issue_qty -= $stock->stock_bal;
                        $stock->stock_bal = 0;
                    } else {
                        $trans_qty = $issue_qty;
                        $stock->stock_bal -= $issue_qty;
                        $issue_qty = 0;
                    }

                    $issued_qty += $trans_qty;

                    InOutTransactionItem::create([
                        'stock_id' => $stock->id,
                        'iotrans_id' => $this->selected_request->id,
                        'dmdcomb' => $this->selected_request->dmdcomb,
                        'dmdctr' => $this->selected_request->dmdctr,
                        'from' => $this->selected_request->request_from,
                        'to' => $this->selected_request->loc_code,
                        'chrgcode' => $stock->chrgcode,
                        'exp_date' => $stock->exp_date,
                        'qty' => $trans_qty,
                        'status' => 'Pending',
                        'user_id' => auth()->id(),
                        'retail_price' => $stock->retail_price,
                        'dmdprdte' => $stock->dmdprdte,
                    ]);
                    $stock->save();

                    $this->logIssue($location_id, $stock, $trans_qty);
                }
            }

            $this->selected_request->issued_qty = $issued_qty;
            $this->selected_request->issued_by = auth()->id();
            $this->selected_request->trans_stat = 'Issued';
            $this->selected_request->remarks_issue = $this->remarks;
            $this->selected_request->save();

            $this->issueModal = false;
            $this->reset('chrgcode', 'issue_qty', 'remarks', 'selected_request', 'available_drugs');
            $this->success('Request issued successfully!');
        } else {
            $this->error('Failed to issue medicine. Selected fund source insufficient stock!');
        }
    }

    public function receiveIssued(InOutTransaction $txn)
    {
        $issued_items = InOutTransactionItem::where('iotrans_id', $txn->id)
            ->where('status', 'Pending')
            ->latest('exp_date')
            ->get();

        if ($issued_items->count()) {
            foreach ($issued_items as $item) {
                $stock = DrugStock::firstOrCreate([
                    'dmdcomb' => $item->dmdcomb,
                    'dmdctr' => $item->dmdctr,
                    'loc_code' => $item->to,
                    'chrgcode' => $item->chrgcode,
                    'exp_date' => $item->exp_date,
                    'retail_price' => $item->retail_price,
                    'dmdprdte' => $item->dmdprdte,
                    'drug_concat' => $item->dm->drug_concat ?? '',
                    'lot_no' => $item->from_stock->lot_no ?? '',
                ]);
                $stock->stock_bal += $item->qty;
                $stock->beg_bal += $item->qty;

                $item->status = 'Received';

                $stock->save();
                $item->save();

                $this->logReceive($item, $stock, $txn->trans_no);
            }
        }

        $txn->trans_stat = 'Received';
        $txn->save();

        $this->success('Transaction successful. All items received!');
    }

    public function cancelTx(InOutTransaction $txn)
    {
        $issued_items = InOutTransactionItem::where('iotrans_id', $txn->id)
            ->where('status', 'Pending')
            ->get();

        if ($issued_items->count()) {
            foreach ($issued_items as $item) {
                $from_stock = $item->from_stock;
                if ($from_stock) {
                    $from_stock->stock_bal += $item->qty;
                    $from_stock->save();
                }
                $item->status = 'Cancelled';
                $item->save();
            }
        }

        $txn->issued_qty = 0;
        $txn->trans_stat = 'Cancelled';
        $txn->save();

        $this->success('Transaction cancelled. All issued items have been returned!');
    }

    public function denyRequest($remarks)
    {
        if ($this->selected_request && $this->selected_request->trans_stat == 'Requested') {
            $this->selected_request->remarks_cancel = $remarks;
            $this->selected_request->trans_stat = 'Declined';
            $this->selected_request->issued_by = auth()->id();
            $this->selected_request->save();

            $this->reset('selected_request', 'available_drugs', 'issueModal');
            $this->warning('Request declined!');
        } else {
            $this->error('Request has already been processed.');
        }
    }

    private function logIssue($location_id, $stock, $qty)
    {
        $log = DrugStockLog::firstOrNew([
            'loc_code' => $location_id,
            'dmdcomb' => $stock->dmdcomb,
            'dmdctr' => $stock->dmdctr,
            'chrgcode' => $stock->chrgcode,
            'unit_cost' => $stock->current_price ? $stock->current_price->acquisition_cost : 0,
            'unit_price' => $stock->retail_price,
            'consumption_id' => null,
        ]);
        $log->transferred += $qty;
        $log->save();

        $card = DrugStockCard::firstOrNew([
            'chrgcode' => $stock->chrgcode,
            'loc_code' => $location_id,
            'dmdcomb' => $stock->dmdcomb,
            'dmdctr' => $stock->dmdctr,
            'exp_date' => $stock->exp_date,
            'stock_date' => date('Y-m-d'),
            'drug_concat' => $stock->drug_concat(),
            'dmdprdte' => $stock->dmdprdte,
            'io_trans_ref_no' => $this->selected_request->trans_no,
        ]);
        $card->iss += $qty;
        $card->bal -= $qty;
        $card->save();
    }

    private function logReceive($item, $stock, $ref_no)
    {
        $log = DrugStockLog::firstOrNew([
            'loc_code' => $item->to,
            'dmdcomb' => $item->dmdcomb,
            'dmdctr' => $item->dmdctr,
            'chrgcode' => $item->chrgcode,
            'unit_cost' => $stock->current_price ? $stock->current_price->acquisition_cost : 0,
            'unit_price' => $item->retail_price,
            'consumption_id' => null,
        ]);
        $log->received += $item->qty;
        $log->save();

        $card = DrugStockCard::firstOrNew([
            'chrgcode' => $item->chrgcode,
            'loc_code' => $item->to,
            'dmdcomb' => $item->dmdcomb,
            'dmdctr' => $item->dmdctr,
            'exp_date' => $stock->exp_date,
            'stock_date' => date('Y-m-d'),
            'drug_concat' => $stock->drug_concat(),
            'dmdprdte' => $item->dmdprdte,
            'io_trans_ref_no' => $ref_no,
        ]);
        $card->rec += $item->qty;
        $card->bal += $item->qty;
        $card->save();
    }
}
