<?php

namespace App\Services\Pharmacy;

use App\Models\References\ServiceType;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class OpdIssuanceClinics
{
    public function forSelectedOrders(array $orderKeys, string $encounterCode): ?string
    {
        $codes = DB::connection('hospital')->table('hospital.dbo.hrxo as drug_order')
            ->leftJoin('webapp.dbo.prescription_data as item', 'item.id', '=', 'drug_order.prescription_data_id')
            ->leftJoin('webapp.dbo.prescription as rx', 'rx.id', '=', 'item.presc_id')
            ->leftJoin('hospital.dbo.hopdlog as visit', function (JoinClause $join) use ($encounterCode) {
                $join->whereRaw(
                    "visit.enccode = COALESCE(NULLIF(rx.enccode, ''), NULLIF(drug_order.original_enccode, ''), ?)",
                    [$encounterCode],
                );
            })
            ->leftJoin('hospital.dbo.htypser as clinic', 'clinic.tscode', '=', 'visit.tscode')
            ->whereIn('drug_order.docointkey', $orderKeys)
            ->distinct()
            ->pluck('clinic.tscode')
            ->map(fn ($code) => trim((string) $code))
            ->unique();

        return $codes->count() === 1 && $codes->first() !== '' ? $codes->first() : null;
    }

    public function options(?string $prescriptionClinic): Collection
    {
        return ServiceType::query()
            ->from('hospital.dbo.htypser as clinic')
            ->where(function ($options) use ($prescriptionClinic) {
                $options->where(function ($active) {
                    $active->where('tsstat', 'A')->where(function ($opd) {
                        // Some clinics serve both admissions and OPD, so their reference location is not OPD.
                        $opd->where('location', 'OPD')->orWhereExists(function ($visits) {
                            $visits->selectRaw('1')->from('hospital.dbo.hopdlog as visit')
                                ->whereColumn('visit.tscode', 'clinic.tscode');
                        });
                    });
                });

                if ($prescriptionClinic !== null) {
                    $options->orWhere('tscode', $prescriptionClinic);
                }
            })
            ->orderBy('tsdesc')
            ->get(['tscode as deptcode', 'tsdesc as deptname'])
            ->map(fn ($clinic) => (object) [
                'deptcode' => trim($clinic->deptcode),
                'deptname' => trim($clinic->deptname),
            ]);
    }
}
