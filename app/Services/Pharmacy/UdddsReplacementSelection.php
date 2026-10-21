<?php

namespace App\Services\Pharmacy;

use Illuminate\Support\Facades\DB;

class UdddsReplacementSelection
{
    public function options($locationId, string $search, array $selected = []): array
    {
        $ids = array_values(array_filter($selected, fn ($id) => is_scalar($id) && ctype_digit((string) $id)));
        if (mb_strlen(trim($search)) < 2 && !$ids) return [];
        return DB::connection('hospital')->table('hospital.dbo.pharm_drug_stocks as stock')
            ->join('hospital.dbo.hdmhdrprice as price', 'price.dmdprdte', '=', 'stock.dmdprdte')
            ->where('stock.loc_code', $locationId)->whereIn('stock.chrgcode', app('chargetable'))
            ->where('stock.exp_date', '>', now()->toDateString())->where('stock.stock_bal', '>', 0)
            ->whereNotNull('price.retail_price')->where('price.retail_price', '>=', 0)
            ->where(function ($query) use ($search, $ids) {
                $query->whereIn('stock.id', $ids);
                if (mb_strlen(trim($search)) >= 2) $query->orWhere('stock.drug_concat', 'like', '%' . trim($search) . '%');
            })->select('stock.*', 'price.retail_price as replacement_price')
            ->orderByRaw('CASE WHEN stock.id IN (' . ($ids ? implode(',', array_map('intval', $ids)) : '0') . ') THEN 0 ELSE 1 END')
            ->orderBy('stock.drug_concat')->orderBy('stock.exp_date')->limit(50 + count($ids))->get()->all();
    }

    public function summary(array $groups, array $choices, array $options): array
    {
        $stocks = [];
        foreach ($options as $stock) $stocks[(string) $stock->id] = $stock;
        $summary = [];
        foreach ($groups as $key => $group) {
            $id = $choices[$key] ?? null;
            if (!is_scalar($id) || !isset($stocks[(string) $id])) continue;
            $stock = $stocks[(string) $id];
            $summary[] = str_replace('_', '', $group['item']->drug_concat ?? $group['item']->dmdcomb)
                . ' -> ' . str_replace('_', '', $stock->drug_concat ?? $stock->dmdcomb)
                . ' (fund ' . $stock->chrgcode . '; ' . $group['qty'] . ' units; '
                . count($group['patients']) . ' patients; ' . $stock->replacement_price . ' per unit)';
        }
        return $summary;
    }

    public static function confirmationKey(array $keys, array $choices, array $funds, $date): string
    {
        sort($keys);
        ksort($choices);
        ksort($funds);
        return hash('sha256', json_encode([$keys, $choices, $funds, $date]));
    }

    // Changes only the supplied in-memory orders. Persistence belongs to charge/issue.
    public function apply(array $items, array $choices, array $options): array
    {
        $stocks = [];
        foreach ($options as $stock) $stocks[(string) $stock->id] = $stock;
        $groups = (new UdddsService())->fundSelectionGroups($items, array_map(fn ($item) => (string) $item->docointkey, $items));
        $originalKeys = [];
        foreach ($items as $order) $originalKeys[(string) $order->docointkey] = UdddsStockAllocator::groupKey($order);
        foreach ($groups as $key => $group) {
            if (empty($choices[$key])) continue;
            $item = $group['item'];
            if ($item->current_available >= $group['qty'] || !empty($item->alternate_funds)) {
                throw new \InvalidArgumentException('Stock or fund availability changed. Clear the replacement and review funds again.');
            }
            $id = $choices[$key];
            if (!is_scalar($id) || !isset($stocks[(string) $id])) throw new \InvalidArgumentException('Select an available replacement stock item.');
            $stock = $stocks[(string) $id];
            if ((string) $stock->dmdcomb === (string) $item->dmdcomb && (string) $stock->dmdctr === (string) $item->dmdctr) {
                throw new \InvalidArgumentException('Choose a different medicine/stock item.');
            }
            foreach ($items as $order) {
                if ($originalKeys[(string) $order->docointkey] !== $key) continue;
                if (!empty($order->pcchrgcod) && !empty($order->uddds_source_docointkey)) throw new \InvalidArgumentException('Adjust the existing daily charge separately before replacing an item.');
                $order->remarks = trim(($order->remarks ?? '') . ' UDDDS replacement for ' . $order->dmdcomb . '/' . $order->dmdctr . ' (fund ' . $order->orderfrom . ').');
                $order->dmdcomb = $stock->dmdcomb;
                $order->dmdctr = $stock->dmdctr;
                $order->orderfrom = $stock->chrgcode;
                $order->pchrgup = $stock->replacement_price;
            }
        }
        return $items;
    }
}
