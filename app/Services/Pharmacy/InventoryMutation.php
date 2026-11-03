<?php

namespace App\Services\Pharmacy;

use Illuminate\Support\Facades\DB;

/** Coordinates inventory writers in both applications, including legacy batch/log updates. */
class InventoryMutation
{
    public function reserveStock($orders, $locationId)
    {
        $required = [];
        foreach ($orders as $order) {
            if (!empty($order->ris)) continue;
            $key = $order->dmdcomb . ':' . $order->dmdctr . ':' . $order->orderfrom;
            if (!isset($required[$key])) $required[$key] = ['order' => $order, 'qty' => 0];
            $required[$key]['qty'] += (float) $order->pchrgqty;
        }
        ksort($required);
        foreach ($required as $entry) {
            $order = $entry['order'];
            $stocks = \App\Models\Pharmacy\Drugs\DrugStock::where('dmdcomb', $order->dmdcomb)
                ->where('dmdctr', $order->dmdctr)->where('chrgcode', $order->orderfrom)
                ->where('loc_code', $locationId)->where('exp_date', '>', date('Y-m-d'))
                ->where('stock_bal', '>', 0)->orderBy('id')->lockForUpdate()->get();
            if ($entry['qty'] <= 0 || (float) $stocks->sum('stock_bal') < $entry['qty']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['stock' => 'Insufficient available stock. Refresh and select the items again.']);
            }
        }
    }

    public function run(callable $operation, ?callable $progress = null)
    {
        $db = DB::connection('hospital');
        return $db->transaction(function () use ($db, $operation, $progress) {
            if ($progress) $progress('waiting');
            if ($db->getDriverName() === 'sqlsrv') {
                // The transaction owns the lock, so SQL Server releases it even on rollback.
                // Keep one shared resource until all legacy summary rows have natural unique keys.
                $result = $db->selectOne("DECLARE @result int; EXEC @result = sp_getapplock @Resource = 'pdims-pharmacy-inventory', @LockMode = 'Exclusive', @LockOwner = 'Transaction', @LockTimeout = 15000; SELECT @result AS result;");
                if (!$result || (int) $result->result < 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['transaction' => 'Inventory is busy. Refresh and try again.']);
                }
            }
            if ($progress) $progress('acquired');
            $result = $operation();
            if ($progress) $progress('committing');
            return $result;
        });
    }
}
