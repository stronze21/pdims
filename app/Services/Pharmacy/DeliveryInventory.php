<?php

namespace App\Services\Pharmacy;

use App\Models\Pharmacy\DeliveryDetail;
use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\Pharmacy\Drugs\DrugStockLog;
use App\Models\Pharmacy\Drugs\DrugStockCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryInventory
{
    private function fail($message)
    {
        throw ValidationException::withMessages(['delivery' => $message]);
    }

    public function authorize($delivery)
    {
        abort_unless(auth()->user() && auth()->user()->can('view-deliveries')
            && (string) auth()->user()->pharm_location_id === (string) $delivery->pharm_location_id, 403);
    }

    private function units($quantity)
    {
        return (int) round((float) $quantity * 100);
    }

    private function audit($delivery, $action, $reason, $entries)
    {
        DB::connection('hospital')->table('pharm_delivery_audits')->insert([
            'delivery_id' => $delivery->id, 'action' => $action,
            'user_id' => (string) auth()->id(), 'user_name' => auth()->user()->name,
            'reason' => $reason, 'entries' => json_encode($entries, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    public function lock($id, $consumptionId)
    {
        DB::connection('hospital')->transaction(function () use ($id, $consumptionId) {
            $delivery = DeliveryDetail::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->authorize($delivery);
            if ($delivery->status !== 'pending') $this->fail('Only pending deliveries can be locked.');
            $items = $delivery->items()->orderBy('id')->lockForUpdate()->get();
            if ($items->isEmpty()) $this->fail('There are no items to add to inventory.');
            $entries = [];
            foreach ($items as $item) {
                if ($this->units($item->qty) <= 0) $this->fail('Every delivery quantity must be positive.');
                $drug = $item->drug;
                if (!$drug) $this->fail('A delivery item has no drug record.');
                $name = method_exists($drug, 'drug_name') ? $drug->drug_name() : $drug->drug_concat;
                $stock = DrugStock::firstOrCreate([
                    'dmdcomb' => $item->dmdcomb, 'dmdctr' => $item->dmdctr,
                    'loc_code' => $item->pharm_location_id, 'chrgcode' => $item->charge_code,
                    'exp_date' => $item->expiry_date, 'retail_price' => $item->retail_price,
                    'drug_concat' => $name, 'lot_no' => $item->lot_no,
                    'dmdnost' => $drug->dmdnost, 'strecode' => $drug->strecode,
                    'formcode' => $drug->formcode, 'rtecode' => $drug->rtecode,
                    'brandname' => $drug->brandname, 'dmdrem' => $drug->dmdrem,
                    'dmdrxot' => $drug->dmdrxot, 'gencode' => $drug->generic->gencode ?? null,
                ]);
                $stock = DrugStock::whereKey($stock->id)->lockForUpdate()->firstOrFail();
                $before = $stock->stock_bal;
                $stock->stock_bal = ($this->units($before) + $this->units($item->qty)) / 100;
                $stock->beg_bal = ($this->units($stock->beg_bal) + $this->units($item->qty)) / 100;
                $stock->dmdprdte = $item->dmdprdte;
                $stock->save();
                $log = DrugStockLog::where([
                    'loc_code' => $item->pharm_location_id, 'dmdcomb' => $item->dmdcomb,
                    'dmdctr' => $item->dmdctr, 'chrgcode' => $item->charge_code,
                    'unit_cost' => $item->unit_price, 'unit_price' => $item->retail_price,
                    'consumption_id' => $consumptionId,
                ])->lockForUpdate()->first();
                if (!$log) {
                    $log = new DrugStockLog;
                    $log->fill([
                        'loc_code' => $item->pharm_location_id, 'dmdcomb' => $item->dmdcomb,
                        'dmdctr' => $item->dmdctr, 'chrgcode' => $item->charge_code,
                        'unit_cost' => $item->unit_price, 'unit_price' => $item->retail_price,
                        'consumption_id' => $consumptionId,
                    ]);
                }
                $log->purchased = ($this->units($log->purchased) + $this->units($item->qty)) / 100;
                $log->save();
                // Use the inventory connection so receipts, balances and audit commit together.
                $card = DrugStockCard::on('hospital')->firstOrNew([
                    'io_trans_ref_no' => $delivery->si_no, 'loc_code' => $item->pharm_location_id,
                    'dmdcomb' => $item->dmdcomb, 'dmdctr' => $item->dmdctr,
                    'chrgcode' => $item->charge_code, 'exp_date' => $item->expiry_date,
                    'stock_date' => now()->format('Y-m-d'),
                    'drug_concat' => method_exists($drug, 'drug_concat') ? $drug->drug_concat() : $name,
                    'dmdprdte' => $item->dmdprdte,
                ]);
                $card->setConnection('hospital');
                if ($card->exists) $card = DrugStockCard::on('hospital')->whereKey($card->id)->lockForUpdate()->firstOrFail();
                $card->rec = ($this->units($card->rec) + $this->units($item->qty)) / 100;
                $card->bal = ($this->units($card->bal) + $this->units($item->qty)) / 100;
                $card->save();
                $entries[] = ['item_id' => $item->id, 'stock_id' => $stock->id,
                    'log_id' => $log->id, 'card_id' => $card->id, 'qty' => $item->qty,
                    'drug' => $name, 'lot_no' => $item->lot_no, 'expiry_date' => $item->expiry_date,
                    'balance_before' => $before, 'balance_after' => $stock->stock_bal];
                $item->status = 'delivered';
                $item->save();
            }
            $delivery->status = 'locked';
            $delivery->save();
            $this->audit($delivery, 'locked', null, $entries);
        });
    }

    private function legacyEntries($delivery, $items)
    {
        $entries = [];
        foreach ($items as $item) {
            $stocks = DrugStock::where([
                'dmdcomb' => $item->dmdcomb, 'dmdctr' => $item->dmdctr,
                'loc_code' => $item->pharm_location_id, 'chrgcode' => $item->charge_code,
                'exp_date' => $item->expiry_date, 'retail_price' => $item->retail_price,
                'lot_no' => $item->lot_no,
            ])->lockForUpdate()->get();
            $logs = DrugStockLog::where([
                'loc_code' => $item->pharm_location_id, 'dmdcomb' => $item->dmdcomb,
                'dmdctr' => $item->dmdctr, 'chrgcode' => $item->charge_code,
                'unit_cost' => $item->unit_price, 'unit_price' => $item->retail_price,
            ])->where('purchased', '>', 0)->lockForUpdate()->get();
            $cards = DrugStockCard::on('hospital')->where([
                'io_trans_ref_no' => $delivery->si_no, 'loc_code' => $item->pharm_location_id,
                'dmdcomb' => $item->dmdcomb, 'dmdctr' => $item->dmdctr,
                'chrgcode' => $item->charge_code, 'exp_date' => $item->expiry_date,
                'dmdprdte' => $item->dmdprdte,
            ])->whereDate('stock_date', $delivery->updated_at->format('Y-m-d'))
                ->lockForUpdate()->get();
            if ($stocks->count() !== 1 || $logs->count() !== 1 || $cards->count() !== 1) {
                $this->fail('Original inventory posting is missing or ambiguous for item '.$item->id.'. Manual reconciliation is required.');
            }
            $entries[] = ['item_id' => $item->id, 'stock_id' => $stocks->first()->id,
                'log_id' => $logs->first()->id, 'card_id' => $cards->first()->id,
                'qty' => $item->qty, 'drug' => $stocks->first()->drug_concat,
                'lot_no' => $item->lot_no, 'expiry_date' => $item->expiry_date];
        }
        return $entries;
    }

    public function revert($id, $reason)
    {
        if (mb_strlen(trim($reason)) < 5 || mb_strlen($reason) > 1000) $this->fail('Enter a reversal reason of 5-1000 characters.');
        DB::connection('hospital')->transaction(function () use ($id, $reason) {
            $delivery = DeliveryDetail::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->authorize($delivery);
            if ($delivery->status !== 'locked') $this->fail('Only locked deliveries can be reverted.');
            $items = $delivery->items()->orderBy('id')->lockForUpdate()->get();
            if ($items->isEmpty() || $items->contains(function ($item) { return $item->status !== 'delivered'; })) {
                $this->fail('Every item must have a completed delivery posting.');
            }
            $posting = DB::connection('hospital')->table('pharm_delivery_audits')
                ->where('delivery_id', $id)->where('action', 'locked')->orderByDesc('id')->first();
            $entries = $posting ? json_decode($posting->entries, true, 512, JSON_THROW_ON_ERROR)
                : $this->legacyEntries($delivery, $items);
            if (count($entries) !== $items->count()) $this->fail('Delivery items do not match the original posting.');
            $audit = [];
            foreach ($entries as $entry) {
                $item = $items->firstWhere('id', $entry['item_id']);
                $qty = $this->units($entry['qty']);
                if (!$item || $qty <= 0 || $qty !== $this->units($item->qty)) $this->fail('Delivery quantity does not match the original posting.');
                $stock = DrugStock::whereKey($entry['stock_id'])->lockForUpdate()->first();
                $log = DrugStockLog::whereKey($entry['log_id'])->lockForUpdate()->first();
                $card = DrugStockCard::on('hospital')->whereKey($entry['card_id'])->lockForUpdate()->first();
                if (!$stock || !$log || !$card) $this->fail('An original inventory posting is missing.');
                foreach (['dmdcomb' => $item->dmdcomb, 'dmdctr' => $item->dmdctr,
                    'loc_code' => $item->pharm_location_id, 'chrgcode' => $item->charge_code,
                    'lot_no' => $item->lot_no] as $field => $value) {
                    if ((string) $stock->$field !== (string) $value) $this->fail('The original batch was moved or reclassified. Manual reconciliation is required.');
                }
                if (\Carbon\Carbon::parse($stock->exp_date)->format('Y-m-d') !== \Carbon\Carbon::parse($item->expiry_date)->format('Y-m-d')
                    || $this->units($stock->retail_price) !== $this->units($item->retail_price)) {
                    $this->fail('The original batch expiry or price has changed. Manual reconciliation is required.');
                }
                if ($this->units($stock->stock_bal) < $qty) {
                    $this->fail('Insufficient balance for '.$entry['drug'].' (lot '.$entry['lot_no'].'): available '.$stock->stock_bal.', required '.$entry['qty'].'. Nothing was reverted.');
                }
                if ($this->units($log->purchased) < $qty || $this->units($card->rec) < $qty || $this->units($card->bal) < $qty) {
                    $this->fail('Original receipt quantities are insufficient. Manual reconciliation is required.');
                }
                // Avoid rewriting a finalized consumption period or its carried balances.
                if ($log->consumption_id) {
                    $period = DB::connection('hospital')->table('pharm_consumption_log_details')
                        ->where('id', $log->consumption_id)->lockForUpdate()->first();
                    if (!$period || $period->status === 'closed' || $period->consumption_to !== null) {
                        $this->fail('The original receipt belongs to a closed consumption period. Manual reconciliation is required.');
                    }
                } elseif ($delivery->updated_at->format('Y-m') !== now()->format('Y-m')) {
                    $this->fail('This receipt belongs to an earlier reporting month. Manual reconciliation is required.');
                }
                $before = $stock->stock_bal;
                $stock->stock_bal = ($this->units($before) - $qty) / 100;
                $stock->beg_bal = ($this->units($stock->beg_bal) - $qty) / 100;
                if ($this->units($stock->beg_bal) < 0) $this->fail('Beginning balance cannot safely be reversed. Manual reconciliation is required.');
                $stock->save();
                // Correct the purchase receipt; never increment issues, consumption or pull-outs.
                $log->purchased = ($this->units($log->purchased) - $qty) / 100;
                $log->save();
                $card->rec = ($this->units($card->rec) - $qty) / 100;
                $card->bal = ($this->units($card->bal) - $qty) / 100;
                $card->save();
                $item->status = 'pending';
                $item->save();
                $entry['balance_before'] = $before;
                $entry['balance_after'] = $stock->stock_bal;
                $audit[] = $entry;
            }
            $delivery->status = 'pending';
            $delivery->save();
            $this->audit($delivery, 'reverted', trim($reason), $audit);
        });
    }
}
