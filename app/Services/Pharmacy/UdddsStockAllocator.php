<?php

namespace App\Services\Pharmacy;

class UdddsStockAllocator
{
    public static function groupKey($item): string
    {
        return md5($item->dmdcomb . '|' . $item->dmdctr . '|' . $item->orderfrom);
    }

    public function allocate(array $items, array $stocks, array $fallbacks): array
    {
        usort($stocks, fn ($a, $b) => [$a->exp_date, (string) $a->id] <=> [$b->exp_date, (string) $b->id]);
        $remaining = [];
        foreach ($stocks as $stock) $remaining[$stock->id] = max(0, (float) $stock->stock_bal);
        $plans = [];
        $needed = [];
        // Reserve each order's own fund first, so fallback cannot take another order's primary allocation.
        foreach ($items as $item) {
            $qty = (float) $item->pchrgqty;
            if ($qty <= 0) return ['ok' => false, 'message' => 'Order quantity must be greater than zero.', 'plans' => []];
            $plans[$item->docointkey] = [];
            $needed[$item->docointkey] = $this->take($item, $item->orderfrom, $qty, $stocks, $remaining, $plans[$item->docointkey]);
        }
        foreach ($items as $item) {
            $qty = $needed[$item->docointkey];
            if ($qty <= 0.000001) continue;
            $alternates = array_values(array_unique(array_filter((array) ($fallbacks[self::groupKey($item)] ?? []))));
            foreach ($alternates as $alternate) {
                if ($qty <= 0.000001) break;
                if ($alternate === $item->orderfrom) continue;
                if (!empty($item->pcchrgcod)) return ['ok' => false, 'message' => 'An already-charged order needs another fund source. Adjust its existing charge separately before using fallback.', 'plans' => []];
                $qty = $this->take($item, $alternate, $qty, $stocks, $remaining, $plans[$item->docointkey]);
            }
            if ($qty > 0.000001) return ['ok' => false, 'message' => 'Insufficient combined stock for ' . ($item->drug_concat ?? $item->dmdcomb) . ': short by ' . $qty . '. Select an alternate fund with enough stock.', 'plans' => []];
        }
        return ['ok' => true, 'plans' => $plans];
    }

    private function take($item, $fund, $qty, array $stocks, array &$remaining, array &$plan): float
    {
        foreach ($stocks as $stock) {
            if ((string) $stock->dmdcomb !== (string) $item->dmdcomb || (string) $stock->dmdctr !== (string) $item->dmdctr
                || $stock->chrgcode !== $fund || $remaining[$stock->id] <= 0) continue;
            $take = min($qty, $remaining[$stock->id]);
            if ($take <= 0) break;
            $plan[] = ['stock' => $stock, 'qty' => $take];
            $remaining[$stock->id] -= $take;
            $qty -= $take;
            if ($qty <= 0.000001) break;
        }
        return max(0, $qty);
    }
}
