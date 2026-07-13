<?php

namespace App\Services\Pharmacy;

use App\Models\PrescriptionReorderLog;
use App\Models\PrescriptionReorderRunLog;
use App\Models\Record\Prescriptions\PrescriptionData;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PrescriptionReactivationService
{
    public function enrichItems(iterable $items, ?Carbon $referenceTime = null): Collection
    {
        $referenceTime ??= now('Asia/Manila');

        return collect($items)
            ->map(fn ($item) => $this->enrichItem($item, $referenceTime));
    }

    public function enrichItem(object|array $item, ?Carbon $referenceTime = null): array
    {
        $referenceTime ??= now('Asia/Manila');

        $row = (object) $item;
        $qtyPerAdministration = (float) ($row->qty ?? 0);
        $totalIssued = (float) ($row->total_issued ?? 0);
        $administrationsPerDay = $this->administrationsPerDay($row->remark ?? null);
        $durationDays = $this->durationDays($row);
        $computedTotalQty = $this->computedTotalQuantity($qtyPerAdministration, $administrationsPerDay, $durationDays);
        $singleDispenseDays = $this->singleDispenseDays($durationDays);
        $singleDispenseQty = $this->computedTotalQuantity($qtyPerAdministration, $administrationsPerDay, $singleDispenseDays);
        $computedRemainingQty = $computedTotalQty === null
            ? null
            : max($computedTotalQty - $totalIssued, 0);
        $allowableRequestQty = $computedRemainingQty === null || $singleDispenseQty === null
            ? $computedRemainingQty
            : min($computedRemainingQty, $singleDispenseQty);
        $scheduleText = isset($row->remark) && trim((string) $row->remark) !== ''
            ? trim((string) $row->remark)
            : null;
        $isArchived = $this->isArchived($row->archive ?? null);
        $isFullyIssued = $computedTotalQty === null
            ? false
            : $totalIssued >= $computedTotalQty;
        $canAutoReactivate = ! $isArchived
            && ($row->stat ?? null) === 'I'
            && $computedTotalQty !== null
            && ! $isFullyIssued;
        $reorderSource = isset($row->reorder_source) ? (string) $row->reorder_source : null;
        $reorderedAt = isset($row->reordered_at) ? (string) $row->reordered_at : null;

        return [
            'id' => (int) ($row->id ?? 0),
            'presc_id' => isset($row->presc_id) ? (int) $row->presc_id : null,
            'enccode' => isset($row->enccode) ? (string) $row->enccode : null,
            'hpercode' => isset($row->hpercode) ? (string) $row->hpercode : null,
            'patient_name' => $this->buildPatientName($row),
            'encounter_type' => isset($row->encounter_type) ? (string) $row->encounter_type : null,
            'encounter_type_label' => $this->encounterTypeLabel($row->encounter_type ?? null),
            'encounter_date' => isset($row->encounter_date) ? (string) $row->encounter_date : null,
            'wardcode' => isset($row->wardcode) ? (string) $row->wardcode : null,
            'wardname' => isset($row->wardname) ? (string) $row->wardname : null,
            'dmdcomb' => isset($row->dmdcomb) ? (string) $row->dmdcomb : null,
            'dmdctr' => isset($row->dmdctr) ? (string) $row->dmdctr : null,
            'drug_concat' => isset($row->drug_concat) ? (string) $row->drug_concat : null,
            'qty' => $qtyPerAdministration,
            'qty_per_administration' => $qtyPerAdministration,
            'administrations_per_day' => $administrationsPerDay,
            'duration_days' => $durationDays,
            'days_to_cover' => $durationDays,
            'computed_total_qty' => $computedTotalQty,
            'computed_full_course_qty' => $computedTotalQty,
            'single_dispense_days' => $singleDispenseDays,
            'single_allowable_dispense_qty' => $singleDispenseQty,
            'allowable_request_qty' => $allowableRequestQty,
            'refill_after_30_days_qty' => $computedTotalQty === null || $singleDispenseQty === null
                ? null
                : max($computedTotalQty - $singleDispenseQty, 0),
            'qty_issued' => $totalIssued,
            'computed_remaining_qty' => $computedRemainingQty,
            'remaining_qty' => $computedRemainingQty,
            'remark' => isset($row->remark) ? (string) $row->remark : null,
            'schedule_text' => $scheduleText,
            'addtl_remarks' => isset($row->addtl_remarks) ? (string) $row->addtl_remarks : null,
            'order_type' => isset($row->order_type) ? (string) $row->order_type : null,
            'tkehome' => $row->tkehome ?? null,
            'frequency' => isset($row->frequency) ? (string) $row->frequency : null,
            'duration' => isset($row->duration) ? (string) $row->duration : null,
            'stat' => isset($row->stat) ? (string) $row->stat : null,
            'archive' => $row->archive ?? null,
            'active_date' => isset($row->active_date) ? (string) $row->active_date : null,
            'is_archived' => $isArchived,
            'is_fully_issued' => $isFullyIssued,
            'can_auto_reactivate' => $canAutoReactivate,
            'needs_manual_review' => $computedTotalQty === null,
            'calculation_notes' => $this->buildNotes($qtyPerAdministration, $administrationsPerDay, $durationDays, $row->remark ?? null),
            'cdoe_summary' => $this->buildCdoeSummary($qtyPerAdministration, $scheduleText, $durationDays),
            'scheduled_reactivation_at' => $canAutoReactivate
                ? $referenceTime->copy()->addDay()->startOfDay()->setTime(7, 0)->toDateTimeString()
                : null,
            'reorder_source' => $reorderSource,
            'reorder_source_label' => $this->reorderSourceLabel($reorderSource),
            'reordered_at' => $reorderedAt,
        ];
    }

    public function enrichItemObject(object|array $item, ?Carbon $referenceTime = null): object
    {
        return (object) $this->enrichItem($item, $referenceTime);
    }

    public function enrichItemObjects(iterable $items, ?Carbon $referenceTime = null): Collection
    {
        return $this->enrichItems($items, $referenceTime)
            ->map(fn (array $item) => (object) $item)
            ->values();
    }

    public function eligibleForReactivation(?Carbon $runAt = null, ?string $hpercode = null, ?string $wardcode = null): Collection
    {
        $runAt ??= now('Asia/Manila');
        $wardcode = $this->normaliseFilterValue($wardcode);

        $sql = "
            SELECT
                pd.id,
                pd.presc_id,
                rx.enccode,
                enctr.hpercode,
                enctr.toecode AS encounter_type,
                enctr.encdate AS encounter_date,
                current_ward.wardcode,
                current_ward.wardname,
                pat.patlast,
                pat.patfirst,
                pat.patmiddle,
                pd.dmdcomb,
                pd.dmdctr,
                pd.qty,
                pd.frequency,
                pd.duration,
                pd.remark,
                pd.addtl_remarks,
                pd.stat,
                pd.archive,
                pd.active_date,
                pd.created_at,
                pd.updated_at,
                dm.drug_concat,
                COALESCE(pdi.total_issued, 0) AS total_issued
            FROM webapp.dbo.prescription_data pd WITH (NOLOCK)
            INNER JOIN webapp.dbo.prescription rx WITH (NOLOCK)
                ON rx.id = pd.presc_id
            INNER JOIN hospital.dbo.henctr enctr WITH (NOLOCK)
                ON rx.enccode = enctr.enccode
            LEFT JOIN hospital.dbo.hopdlog opd WITH (NOLOCK)
                ON enctr.enccode = opd.enccode
            LEFT JOIN hospital.dbo.herlog er WITH (NOLOCK)
                ON enctr.enccode = er.enccode
            LEFT JOIN hospital.dbo.hadmlog adm WITH (NOLOCK)
                ON enctr.enccode = adm.enccode
            INNER JOIN hospital.dbo.hperson pat WITH (NOLOCK)
                ON enctr.hpercode = pat.hpercode
            INNER JOIN hospital.dbo.hdmhdr dm WITH (NOLOCK)
                ON pd.dmdcomb = dm.dmdcomb
                AND pd.dmdctr = dm.dmdctr
            OUTER APPLY (
                SELECT TOP 1
                    pat_room.wardcode,
                    ward.wardname
                FROM hospital.dbo.hpatroom pat_room WITH (NOLOCK)
                INNER JOIN hospital.dbo.hward ward WITH (NOLOCK)
                    ON pat_room.wardcode = ward.wardcode
                WHERE pat_room.enccode = rx.enccode
                    AND pat_room.patrmstat = 'A'
                ORDER BY pat_room.hprdate DESC
            ) current_ward
            LEFT JOIN (
                SELECT presc_data_id, SUM(qtyissued) AS total_issued
                FROM webapp.dbo.prescription_data_issued WITH (NOLOCK)
                GROUP BY presc_data_id
            ) pdi
                ON pdi.presc_data_id = pd.id
            WHERE pd.stat = 'I'
                AND (pd.archive IS NULL OR pd.archive = 0)
                AND enctr.toecode IN ('ADM', 'OPDAD', 'ERADM')
                AND adm.admstat = 'A'
                AND adm.admdate > '2026-07-01'
                AND adm.disdate IS NULL
                AND pd.remark IS NOT NULL
                AND LTRIM(RTRIM(pd.remark)) <> ''
                AND (
                    pd.duration IS NOT NULL
                    OR pd.frequency IS NOT NULL
                )
        ";

        $bindings = [];

        if ($hpercode !== null) {
            $sql .= '
                AND EXISTS (
                    SELECT 1
                    WHERE enctr.hpercode = ?
                )
            ';
            $bindings[] = $hpercode;
        }

        if ($wardcode !== null) {
            $sql .= '
                AND current_ward.wardcode = ?
            ';
            $bindings[] = $wardcode;
        }

        $sql .= '
            ORDER BY pd.id ASC
        ';

        $rows = DB::connection('webapp')->select($sql, $bindings);

        return $this->enrichItems($rows, $runAt)
            ->filter(fn (array $item) => $item['can_auto_reactivate'])
            ->values();
    }

    public function reactivateEligible(?Carbon $runAt = null, bool $dryRun = false, ?string $wardcode = null): array
    {
        return $this->runReorder($runAt, $dryRun, 'auto', null, null, $wardcode);
    }

    public function runReorder(
        ?Carbon $runAt = null,
        bool $dryRun = false,
        string $source = 'auto',
        ?string $performedBy = null,
        ?string $notes = null,
        ?string $wardcode = null
    ): array {
        $runAt ??= now('Asia/Manila');
        $wardcode = $this->normaliseFilterValue($wardcode);
        $runNotes = $this->appendWardFilterNote($notes, $wardcode);

        if ($source === 'auto' && ! $dryRun && $this->hasManualRunToday($runAt)) {
            $skipNotes = $this->appendWardFilterNote(
                $notes ?: 'Skipped automatic reorder because a manual reorder already ran on the same date.',
                $wardcode
            );

            PrescriptionReorderRunLog::query()->create([
                'source' => $source,
                'status' => 'skipped_manual_exists',
                'dry_run' => false,
                'reordered_count' => 0,
                'run_at' => $runAt,
                'performed_by' => $performedBy,
                'notes' => $skipNotes,
            ]);

            return [
                'count' => 0,
                'items' => [],
                'run_at' => $runAt->toDateTimeString(),
                'dry_run' => false,
                'source' => $source,
                'status' => 'skipped_manual_exists',
                'message' => 'Skipped automatic reorder because a manual reorder already ran today.',
                'wardcode' => $wardcode,
            ];
        }

        $eligibleItems = $this->eligibleForReactivation($runAt, null, $wardcode);
        $status = $eligibleItems->isNotEmpty() ? 'completed' : 'no_items';

        if (! $dryRun && $eligibleItems->isNotEmpty()) {
            foreach ($eligibleItems as $item) {
                DB::connection('webapp')
                    ->table('webapp.dbo.prescription_data')
                    ->where('id', $item['id'])
                    ->update([
                        'stat' => 'A',
                        'active_date' => $runAt,
                        'updated_at' => $runAt,
                    ]);

                $this->logReorder(
                    (int) $item['id'],
                    $source,
                    $runAt,
                    $this->appendWardFilterNote($notes ?: 'Reordered by schedule/process', $wardcode),
                    $performedBy
                );
            }
        }

        PrescriptionReorderRunLog::query()->create([
            'source' => $source,
            'status' => $status,
            'dry_run' => $dryRun,
            'reordered_count' => $eligibleItems->count(),
            'run_at' => $runAt,
            'performed_by' => $performedBy,
            'notes' => $runNotes,
        ]);

        return [
            'count' => $eligibleItems->count(),
            'items' => $eligibleItems->all(),
            'run_at' => $runAt->toDateTimeString(),
            'dry_run' => $dryRun,
            'source' => $source,
            'status' => $status,
            'wardcode' => $wardcode,
        ];
    }

    public function reactivatedToday(?Carbon $date = null, ?string $hpercode = null): Collection
    {
        $date ??= now('Asia/Manila');

        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $logs = PrescriptionReorderLog::query()
            ->whereBetween('reordered_at', [$start, $end])
            ->orderByDesc('reordered_at')
            ->get()
            ->unique('prescription_data_id')
            ->values();

        if ($logs->isEmpty()) {
            return collect();
        }

        $logMap = $logs->keyBy('prescription_data_id');
        $ids = $logMap->keys()->values();

        $rows = collect();

        foreach ($ids->chunk(1000) as $chunkedIds) {
            $placeholders = implode(',', array_fill(0, $chunkedIds->count(), '?'));

            $sql = "
            SELECT
                pd.id,
                pd.presc_id,
                rx.enccode,
                enctr.hpercode,
                enctr.toecode AS encounter_type,
                enctr.encdate AS encounter_date,
                pat.patlast,
                pat.patfirst,
                pat.patmiddle,
                pd.dmdcomb,
                pd.dmdctr,
                pd.qty,
                pd.frequency,
                pd.duration,
                pd.remark,
                pd.addtl_remarks,
                pd.stat,
                pd.archive,
                pd.active_date,
                pd.created_at,
                pd.updated_at,
                dm.drug_concat,
                COALESCE(pdi.total_issued, 0) AS total_issued
            FROM webapp.dbo.prescription_data pd WITH (NOLOCK)
            INNER JOIN webapp.dbo.prescription rx WITH (NOLOCK)
                ON rx.id = pd.presc_id
            INNER JOIN hospital.dbo.henctr enctr WITH (NOLOCK)
                ON rx.enccode = enctr.enccode
            INNER JOIN hospital.dbo.hperson pat WITH (NOLOCK)
                ON enctr.hpercode = pat.hpercode
            INNER JOIN hospital.dbo.hdmhdr dm WITH (NOLOCK)
                ON pd.dmdcomb = dm.dmdcomb
                AND pd.dmdctr = dm.dmdctr
            LEFT JOIN (
                SELECT presc_data_id, SUM(qtyissued) AS total_issued
                FROM webapp.dbo.prescription_data_issued WITH (NOLOCK)
                GROUP BY presc_data_id
            ) pdi
                ON pdi.presc_data_id = pd.id
            WHERE pd.id IN ({$placeholders})
                AND enctr.toecode IN ('ADM', 'OPDAD', 'ERADM')
        ";

            $bindings = $chunkedIds->values()->all();

            if ($hpercode !== null) {
                $sql .= ' AND enctr.hpercode = ? ';
                $bindings[] = $hpercode;
            }

            $chunkRows = DB::connection('webapp')->select($sql, $bindings);

            $rows = $rows->merge($chunkRows);
        }

        $rows = $rows->map(function ($row) use ($logMap) {
            $log = $logMap->get($row->id);

            $row->reorder_source = $log?->source;
            $row->reordered_at = optional($log?->reordered_at)->toDateTimeString() ?? $log?->reordered_at;

            return $row;
        });

        return $this->enrichItems($rows, $date)
            ->sortByDesc('reordered_at')
            ->values();
    }

    public function reactivatesTomorrow(?Carbon $date = null, ?string $hpercode = null): Collection
    {
        $date ??= now('Asia/Manila');
        $tomorrow = $date->copy()->addDay()->startOfDay()->setTime(7, 0);

        return $this->eligibleForReactivation($date, $hpercode)
            ->map(function (array $item) use ($tomorrow) {
                $item['scheduled_reactivation_at'] = $tomorrow->toDateTimeString();
                $item['reorder_source'] = 'auto';
                $item['reorder_source_label'] = $this->reorderSourceLabel('auto');

                return $item;
            })
            ->values();
    }

    public function logReorder(int $prescriptionDataId, string $source, ?Carbon $reorderedAt = null, ?string $notes = null, ?string $performedBy = null): void
    {
        $reorderedAt ??= now('Asia/Manila');
        $prescriptionData = PrescriptionData::query()->find($prescriptionDataId);

        PrescriptionReorderLog::query()->create([
            'prescription_data_id' => $prescriptionDataId,
            'prescription_id' => $prescriptionData?->presc_id,
            'source' => $source,
            'reordered_at' => $reorderedAt,
            'performed_by' => $performedBy,
            'notes' => $notes,
        ]);
    }

    public function hasActiveEncounter(int $prescriptionDataId): bool
    {
        $row = DB::connection('webapp')->selectOne('
            SELECT TOP 1
                enctr.toecode,
                enctr.encstat,
                opd.opdstat,
                opd.opddtedis,
                er.erstat,
                er.erdtedis,
                adm.admstat,
                adm.disdate
            FROM webapp.dbo.prescription_data pd WITH (NOLOCK)
            INNER JOIN webapp.dbo.prescription rx WITH (NOLOCK)
                ON rx.id = pd.presc_id
            INNER JOIN hospital.dbo.henctr enctr WITH (NOLOCK)
                ON enctr.enccode = rx.enccode
            LEFT JOIN hospital.dbo.hopdlog opd WITH (NOLOCK)
                ON enctr.enccode = opd.enccode
            LEFT JOIN hospital.dbo.herlog er WITH (NOLOCK)
                ON enctr.enccode = er.enccode
            LEFT JOIN hospital.dbo.hadmlog adm WITH (NOLOCK)
                ON enctr.enccode = adm.enccode
            WHERE pd.id = ?
        ', [$prescriptionDataId]);

        if (! $row) {
            return false;
        }

        return match ($row->toecode ?? null) {
            'ADM', 'OPDAD', 'ERADM' => ($row->admstat ?? null) === 'A' && ($row->disdate ?? null) === null,
            default => false,
        };
    }

    public function latestRun(): ?PrescriptionReorderRunLog
    {
        return PrescriptionReorderRunLog::query()
            ->orderByDesc('run_at')
            ->first();
    }

    public function runLogs(int $limit = 20): Collection
    {
        return PrescriptionReorderRunLog::query()
            ->orderByDesc('run_at')
            ->limit($limit)
            ->get();
    }

    public function recentReorderLogs(int $limit = 20): Collection
    {
        return PrescriptionReorderLog::query()
            ->orderByDesc('reordered_at')
            ->limit($limit)
            ->get();
    }

    public function hasCompletedRunToday(?Carbon $date = null): bool
    {
        $date ??= now('Asia/Manila');

        return PrescriptionReorderRunLog::query()
            ->whereDate('run_at', $date->toDateString())
            ->where('dry_run', false)
            ->exists();
    }

    public function hasManualRunToday(?Carbon $date = null): bool
    {
        $date ??= now('Asia/Manila');

        return PrescriptionReorderRunLog::query()
            ->whereDate('run_at', $date->toDateString())
            ->where('dry_run', false)
            ->where('source', 'manual')
            ->whereIn('status', ['completed', 'no_items'])
            ->exists();
    }

    public function todayRunLog(?Carbon $date = null): ?PrescriptionReorderRunLog
    {
        $date ??= now('Asia/Manila');

        return PrescriptionReorderRunLog::query()
            ->whereDate('run_at', $date->toDateString())
            ->where('dry_run', false)
            ->orderByDesc('run_at')
            ->first();
    }

    public function administrationsPerDay(?string $remark): ?float
    {
        if ($remark === null) {
            return null;
        }

        $normalized = strtolower(trim($remark));

        if ($normalized === '') {
            return null;
        }

        $specialCases = [
            'prn',
            'now',
            'stat',
            'or use',
            'diluent',
            'drip',
            'for cardiac mission',
            '1 dose for onco',
            'intra-operative use',
        ];

        if (in_array($normalized, $specialCases, true)) {
            return null;
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*x\s*a\s*day/', $normalized, $matches)) {
            return (float) $matches[1];
        }

        if (preg_match('/every\s+(\d+(?:\.\d+)?)\s*(hour|hours|hr|hrs|h)\b/', $normalized, $matches)) {
            $hours = (float) $matches[1];

            return $hours > 0 ? 24 / $hours : null;
        }

        return match ($normalized) {
            'od', 'once daily', 'every 24 hours', 'q24', 'q24h' => 1.0,
            'bid', 'twice daily', 'every 12 hours', 'q12', 'q12h' => 2.0,
            'tid', 'three times daily', 'every 8 hours', 'q8', 'q8h' => 3.0,
            'qid', 'four times daily', 'every 6 hours', 'q6', 'q6h' => 4.0,
            'every 4 hours', 'q4', 'q4h' => 6.0,
            default => null,
        };
    }

    public function durationDays(object|array $item): ?int
    {
        $row = (object) $item;
        $duration = $this->positiveInt($row->duration ?? null);

        if ($duration !== null) {
            return $duration;
        }

        return $this->positiveInt($row->frequency ?? null);
    }

    private function computedTotalQuantity(float $qtyPerAdministration, ?float $administrationsPerDay, ?int $durationDays): ?float
    {
        if ($qtyPerAdministration <= 0 || $administrationsPerDay === null || $durationDays === null) {
            return null;
        }

        return round($qtyPerAdministration * $administrationsPerDay * $durationDays, 2);
    }

    private function singleDispenseDays(?int $durationDays): ?int
    {
        if ($durationDays === null) {
            return null;
        }

        return min($durationDays, 30);
    }

    private function positiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }

    private function isArchived(mixed $archive): bool
    {
        return $archive !== null && (string) $archive !== '' && (string) $archive !== '0';
    }

    private function normaliseFilterValue(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function appendWardFilterNote(?string $notes, ?string $wardcode): ?string
    {
        if ($wardcode === null) {
            return $notes;
        }

        $wardNote = "Ward filter: {$wardcode}.";

        if ($notes === null || trim($notes) === '') {
            return $wardNote;
        }

        return rtrim($notes).' '.$wardNote;
    }

    private function buildNotes(float $qtyPerAdministration, ?float $administrationsPerDay, ?int $durationDays, ?string $remark): string
    {
        if ($administrationsPerDay === null) {
            return 'Unable to auto-compute administrations per day from remark: '.trim((string) $remark);
        }

        if ($durationDays === null) {
            return 'Unable to auto-compute duration days from duration/frequency fields.';
        }

        return sprintf(
            'Computed as %s qty/admin x %s admins/day x %s day(s). If the prescribed duration exceeds 30 days, each single dispense/request is capped at 30 days only.',
            rtrim(rtrim(number_format($qtyPerAdministration, 2, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format($administrationsPerDay, 2, '.', ''), '0'), '.'),
            $durationDays
        );
    }

    private function buildCdoeSummary(float $qtyPerAdministration, ?string $scheduleText, ?int $durationDays): string
    {
        $parts = [
            sprintf(
                '%s per administration',
                rtrim(rtrim(number_format($qtyPerAdministration, 2, '.', ''), '0'), '.')
            ),
        ];

        if ($scheduleText !== null) {
            $parts[] = $scheduleText;
        }

        if ($durationDays !== null) {
            $parts[] = "for {$durationDays} day(s)";
        }

        return implode(', ', $parts);
    }

    private function buildPatientName(object $row): ?string
    {
        $parts = array_filter([
            isset($row->patlast) ? trim((string) $row->patlast) : null,
            isset($row->patfirst) ? trim((string) $row->patfirst) : null,
            isset($row->patmiddle) ? trim((string) $row->patmiddle) : null,
        ]);

        if (empty($parts)) {
            return null;
        }

        $last = array_shift($parts);

        return trim($last.(empty($parts) ? '' : ', '.implode(' ', $parts)));
    }

    private function encounterTypeLabel(?string $type): ?string
    {
        return match ($type) {
            'OPD' => 'Out-Patient',
            'ER' => 'Emergency Room',
            'ADM' => 'Admission',
            'ERADM' => 'ER to Admission',
            'OPDAD' => 'OPD to Admission',
            'WALKN' => 'Walk-In',
            null, '' => null,
            default => $type,
        };
    }

    private function reorderSourceLabel(?string $source): ?string
    {
        return match ($source) {
            'auto' => 'Automatic',
            'manual' => 'Manual',
            null, '' => null,
            default => ucfirst((string) $source),
        };
    }
}
