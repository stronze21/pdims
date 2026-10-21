<?php

namespace App\Services\Pharmacy;

class UdddsWardPrint
{
    public static function group(array $items): array
    {
        $wards = [];
        foreach ($items as $item) {
            if (($item->estatus ?? '') !== 'S' && (float) ($item->qtyissued ?? 0) <= 0) continue;
            $ward = trim($item->wardname ?? '') ?: 'Unassigned ward';
            $encounter = (string) $item->enccode;
            if (!isset($wards[$ward][$encounter])) {
                $wards[$ward][$encounter] = ['name' => trim(($item->patlast ?? '') . ', ' . ($item->patfirst ?? '') . ' ' . ($item->patmiddle ?? '') . ' ' . ($item->patsuffix ?? '')), 'items' => []];
            }
            $wards[$ward][$encounter]['items'][] = [
                'medicine' => str_replace('_', '', $item->drug_concat ?? $item->dmdcomb ?? ''),
                'qty' => (float) ($item->qtyissued ?? 0) > 0 ? (float) $item->qtyissued : (float) $item->pchrgqty,
            ];
        }
        ksort($wards, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($wards as &$patients) {
            uasort($patients, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
            foreach ($patients as &$patient) usort($patient['items'], fn ($a, $b) => strnatcasecmp($a['medicine'], $b['medicine']));
            unset($patient);
        }
        unset($patients);
        return $wards;
    }
}
