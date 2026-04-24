<?php

namespace App\Livewire\Pharmacy\Drugs\Concerns;

use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\Pharmacy\Drugs\DrugStockCard;
use App\Models\Pharmacy\Drugs\DrugStockLog;
use App\Models\Pharmacy\Drugs\InOutTransaction;
use App\Models\Pharmacy\Drugs\InOutTransactionItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

trait HandlesIoTransactionReturns
{
    public function canReturnReceived(InOutTransaction $txn): bool
    {
        if ($txn->trans_stat !== 'Received' || $txn->loc_code != auth()->user()->pharm_location_id) {
            return false;
        }

        $received_items = InOutTransactionItem::where('iotrans_id', $txn->id)
            ->where('status', 'Received')
            ->get();

        if ($received_items->isEmpty()) {
            return false;
        }

        foreach ($received_items as $item) {
            $from_stock = DrugStock::find($item->stock_id);
            $received_stock = $this->findReceivedStock($item, $from_stock);

            if (!$from_stock || !$received_stock || $received_stock->stock_bal < $item->qty) {
                return false;
            }
        }

        return true;
    }

    public function returnReceived(InOutTransaction $txn)
    {
        if ($txn->trans_stat !== 'Received') {
            $this->error('Only received IO transactions can be returned.');
            return;
        }

        if ($txn->loc_code != auth()->user()->pharm_location_id) {
            $this->error('Only the receiving location can return this item.');
            return;
        }

        $received_items = InOutTransactionItem::where('iotrans_id', $txn->id)
            ->where('status', 'Received')
            ->get();

        if ($received_items->isEmpty()) {
            $this->error('No received items found to return.');
            return;
        }

        try {
            DB::connection('hospital')->transaction(function () use ($txn, $received_items) {
                $returned_qty = 0;

                foreach ($received_items as $item) {
                    $from_stock = DrugStock::whereKey($item->stock_id)->lockForUpdate()->first();
                    $received_stock = $this->findReceivedStock($item, $from_stock);

                    if (!$from_stock) {
                        throw new RuntimeException('Original issuing stock could not be found.');
                    }

                    if (!$received_stock || $received_stock->stock_bal < $item->qty) {
                        throw new RuntimeException('This exact received item no longer has enough available quantity to return.');
                    }

                    $received_stock->stock_bal -= $item->qty;
                    $received_stock->beg_bal = max(0, ($received_stock->beg_bal ?? 0) - $item->qty);
                    $received_stock->save();

                    $from_stock->stock_bal += $item->qty;
                    $from_stock->save();

                    $item->status = 'Returned';
                    $item->save();

                    $this->logReturnedReceivedItem($item, $received_stock, $from_stock, $txn->trans_no);
                    $returned_qty += $item->qty;
                }

                $txn->received_qty = max(0, ($txn->received_qty ?? $txn->issued_qty ?? 0) - $returned_qty);
                $txn->trans_stat = 'Returned';
                $txn->save();
            });

            $this->success('Received item returned successfully!');
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
        }
    }

    private function findReceivedStock($item, $from_stock = null)
    {
        return DrugStock::where('dmdcomb', $item->dmdcomb)
            ->where('dmdctr', $item->dmdctr)
            ->where('loc_code', $item->to)
            ->where('chrgcode', $item->chrgcode)
            ->whereDate('exp_date', $item->exp_date)
            ->where('retail_price', $item->retail_price)
            ->where('dmdprdte', $item->dmdprdte)
            ->where(function ($query) use ($from_stock) {
                $lot_no = $from_stock->lot_no ?? '';

                $query->where('lot_no', $lot_no);

                if ($lot_no === '') {
                    $query->orWhereNull('lot_no');
                }
            })
            ->lockForUpdate()
            ->first();
    }

    private function logReturnedReceivedItem($item, $received_stock, $from_stock, $ref_no)
    {
        $requestor_log = DrugStockLog::firstOrNew([
            'loc_code' => $item->to,
            'dmdcomb' => $item->dmdcomb,
            'dmdctr' => $item->dmdctr,
            'chrgcode' => $item->chrgcode,
            'unit_cost' => $received_stock->current_price ? $received_stock->current_price->acquisition_cost : 0,
            'unit_price' => $item->retail_price,
            'consumption_id' => null,
        ]);
        $requestor_log->received = max(0, ($requestor_log->received ?? 0) - $item->qty);
        $requestor_log->save();

        $requestor_card = DrugStockCard::firstOrNew([
            'chrgcode' => $item->chrgcode,
            'loc_code' => $item->to,
            'dmdcomb' => $item->dmdcomb,
            'dmdctr' => $item->dmdctr,
            'exp_date' => $received_stock->exp_date,
            'stock_date' => date('Y-m-d'),
            'drug_concat' => $received_stock->drug_concat(),
            'dmdprdte' => $item->dmdprdte,
            'io_trans_ref_no' => $ref_no,
        ]);
        $requestor_card->rec = max(0, ($requestor_card->rec ?? 0) - $item->qty);
        $requestor_card->bal -= $item->qty;
        $requestor_card->save();

        $issuer_log = DrugStockLog::firstOrNew([
            'loc_code' => $item->from,
            'dmdcomb' => $item->dmdcomb,
            'dmdctr' => $item->dmdctr,
            'chrgcode' => $item->chrgcode,
            'unit_cost' => $from_stock->current_price ? $from_stock->current_price->acquisition_cost : 0,
            'unit_price' => $item->retail_price,
            'consumption_id' => null,
        ]);
        $issuer_log->transferred = max(0, ($issuer_log->transferred ?? 0) - $item->qty);
        $issuer_log->save();

        $issuer_card = DrugStockCard::firstOrNew([
            'chrgcode' => $item->chrgcode,
            'loc_code' => $item->from,
            'dmdcomb' => $item->dmdcomb,
            'dmdctr' => $item->dmdctr,
            'exp_date' => $from_stock->exp_date,
            'stock_date' => date('Y-m-d'),
            'drug_concat' => $from_stock->drug_concat(),
            'dmdprdte' => $item->dmdprdte,
            'io_trans_ref_no' => $ref_no,
        ]);
        $issuer_card->iss = max(0, ($issuer_card->iss ?? 0) - $item->qty);
        $issuer_card->bal += $item->qty;
        $issuer_card->save();
    }
}
