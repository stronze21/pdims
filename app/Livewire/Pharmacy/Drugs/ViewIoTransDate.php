<?php

namespace App\Livewire\Pharmacy\Drugs;

use App\Livewire\Pharmacy\Drugs\Concerns\HandlesIoTransactionReturns;
use App\Models\Pharmacy\Drug;
use App\Models\Pharmacy\DrugGroup;
use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\Pharmacy\Drugs\DrugStockCard;
use App\Models\Pharmacy\Drugs\DrugStockLog;
use App\Models\Pharmacy\Drugs\InOutTransaction;
use App\Models\Pharmacy\Drugs\InOutTransactionItem;
use App\Models\Pharmacy\PharmLocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class ViewIoTransDate extends Component
{
    use Toast;
    use HandlesIoTransactionReturns;

    public $date;
    public $search = '';
    public $selected_request;
    public $chrgcode = '';
    public $issue_qty = 0;
    public $remarks = '';
    public $available_drugs = [];
    public $issueModal = false;
    public $updateItemModal = false;
    public $updating_transaction = null;
    public $same_generic_drugs = [];
    public $new_stock_id = '';

    public function mount($date)
    {
        $this->date = $date;
    }

    public function render()
    {
        $date_from = Carbon::parse($this->date)->startOfDay();
        $date_to = Carbon::parse($this->date)->endOfDay();

        $trans = InOutTransaction::whereBetween('created_at', [$date_from, $date_to])
            ->when($this->search, function ($query) {
                $query->whereHas('drug', function ($q) {
                    $q->where('drug_concat', 'like', '%' . $this->search . '%');
                });
            })
            ->with(['drug', 'charge', 'location', 'from_location', 'items'])
            ->where(function ($query) {
                $query->where('loc_code', auth()->user()->pharm_location_id)
                    ->orWhere('request_from', auth()->user()->pharm_location_id);
            })
            ->latest()
            ->get();

        return view('livewire.pharmacy.drugs.view-io-trans-date', [
            'trans' => $trans,
        ]);
    }

    public function viewByRef($trans_no)
    {
        return $this->redirect(route('inventory.io-trans.view-ref', ['reference_no' => $trans_no]), navigate: true);
    }

    public function openUpdateItem(InOutTransaction $txn)
    {
        if ($txn->trans_stat !== 'Requested') {
            $this->error('Only items with "Requested" status can be updated.');
            return;
        }

        $this->updating_transaction = $txn;
        $drug = $txn->drug;

        if ($drug && $drug->generic) {
            $gencode = $drug->generic->gencode;
            $grpcodes = DrugGroup::where('gencode', $gencode)->pluck('grpcode');

            $this->same_generic_drugs = DB::select("
                SELECT d.dmdcomb, d.dmdctr, d.drug_concat, COALESCE(s.total_stock, 0) as total_stock
                FROM hdmhdr d
                LEFT JOIN (
                    SELECT dmdcomb, dmdctr, SUM(stock_bal) as total_stock
                    FROM pharm_drug_stocks
                    WHERE loc_code = ?
                        AND stock_bal > 0
                        AND exp_date > GETDATE()
                    GROUP BY dmdcomb, dmdctr
                ) s ON d.dmdcomb = s.dmdcomb AND d.dmdctr = s.dmdctr
                WHERE d.dmdstat = 'A'
                    AND d.drug_concat IS NOT NULL
                    AND d.grpcode IN (" . implode(',', array_fill(0, count($grpcodes), '?')) . ")
                    AND COALESCE(s.total_stock, 0) > 0
                ORDER BY d.drug_concat
            ", array_merge([$txn->request_from], $grpcodes->toArray()));
        } else {
            $this->same_generic_drugs = [];
        }

        $this->new_stock_id = $txn->dmdcomb . ',' . $txn->dmdctr;
        $this->updateItemModal = true;
    }

    public function updateRequestedItem()
    {
        if (!$this->updating_transaction || $this->updating_transaction->trans_stat !== 'Requested') {
            $this->error('Only items with "Requested" status can be updated.');
            return;
        }

        if (!$this->new_stock_id) {
            $this->error('Please select an item.');
            return;
        }

        $dm = explode(',', $this->new_stock_id);
        $this->updating_transaction->dmdcomb = $dm[0];
        $this->updating_transaction->dmdctr = $dm[1];
        $this->updating_transaction->save();

        $this->reset('updating_transaction', 'same_generic_drugs', 'new_stock_id', 'updateItemModal');
        $this->success('Requested item updated successfully!');
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
            'remarks' => ['nullable', 'string', 'max:255'],
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

        $received_qty = 0;

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
                $received_qty += $item->qty;
            }
        }

        $txn->received_qty = $received_qty;
        $txn->received_by = auth()->id();
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
