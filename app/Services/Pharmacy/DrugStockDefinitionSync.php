<?php

namespace App\Services\Pharmacy;

use Illuminate\Support\Facades\DB;

class DrugStockDefinitionSync
{
    public function syncDrug(string $dmdcomb, string $dmdctr, ?string $location = null): int
    {
        $db = DB::connection('hospital');
        $drug = $db->table('hdmhdr as d')
            ->where('d.dmdcomb', $dmdcomb)->where('d.dmdctr', $dmdctr)
            ->select('d.dmdnost', 'd.strecode')
            ->selectRaw(DrugDescription::fromMaster('d', $db->getDriverName()).' AS description')
            ->first();

        if (! $drug || $drug->description === null) {
            return 0;
        }

        return $db->table('pharm_drug_stocks')
            ->where('dmdcomb', $dmdcomb)->where('dmdctr', $dmdctr)
            ->when($location !== null, fn ($query) => $query->where('loc_code', $location))
            ->update([
                'dmdnost' => $drug->dmdnost,
                'strecode' => $drug->strecode,
                // Legacy dispensing splits this stored value on "_," into two lines.
                'drug_concat' => $drug->description,
            ]);
    }

    public function syncLocation(string $location): int
    {
        return DB::connection('hospital')->transaction(function () use ($location) {
            $drugs = DB::connection('hospital')->table('pharm_drug_stocks')
                ->where('loc_code', $location)->select('dmdcomb', 'dmdctr')->distinct()->get();
            $updated = 0;
            foreach ($drugs as $drug) {
                $updated += $this->syncDrug((string) $drug->dmdcomb, (string) $drug->dmdctr, $location);
            }

            return $updated;
        });
    }
}
