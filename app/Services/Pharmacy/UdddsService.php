<?php

namespace App\Services\Pharmacy;

use App\Models\Pharmacy\Dispensing\DrugOrder;
use App\Models\Pharmacy\Dispensing\OrderChargeCode;
use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\Pharmacy\Drugs\DrugStockCard;
use App\Models\Pharmacy\Drugs\DrugStockIssue;
use App\Models\Pharmacy\Drugs\DrugStockLog;
use App\Models\Record\Prescriptions\PrescriptionDataIssued;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UdddsService
{
    public static function hasHrxoColumns(): bool
    {
        return Schema::connection('hospital')->hasColumns('hrxo', [
            'order_type', 'is_uddds', 'uddds_start_date', 'uddds_end_date', 'uddds_source_docointkey',
        ]);
    }

    public static function hrxoSelectColumns(): string
    {
        if (self::hasHrxoColumns()) {
            return 'hrxo.order_type, hrxo.is_uddds, hrxo.uddds_start_date, hrxo.uddds_end_date, hrxo.uddds_source_docointkey, ' . (self::hasIntervalColumn() ? 'hrxo.uddds_interval_days' : 'CAST(NULL AS INT) AS uddds_interval_days');
        }

        return "CAST(NULL AS VARCHAR(20)) AS order_type, CAST(0 AS BIT) AS is_uddds, CAST(NULL AS DATETIME) AS uddds_start_date, CAST(NULL AS DATETIME) AS uddds_end_date, CAST(NULL AS VARCHAR(50)) AS uddds_source_docointkey, CAST(NULL AS INT) AS uddds_interval_days";
    }

    public function schemaMissingMessage(): string
    {
        return 'The hospital order table is missing required UDDDS columns. Contact your administrator to apply the UDDDS database update.';
    }


    public static function hasIntervalColumn(): bool
    {
        try {
            return Schema::connection('hospital')->hasColumn('hrxo', 'uddds_interval_days');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function intervalSelect(): string
    {
        return self::hasIntervalColumn() ? 'hrxo.uddds_interval_days' : 'CAST(NULL AS INT) AS uddds_interval_days';
    }

    private function enrollmentSchedule($start, $end, $interval): array
    {
        try {
            $days = UdddsSchedule::interval($interval);
            UdddsSchedule::due($start, $end, $days, $start);
            if ($days !== 1 && !self::hasIntervalColumn()) {
                return ['ok' => false, 'message' => 'Interval enrollment requires the UDDDS interval database update. Contact your administrator.'];
            }
            return ['ok' => true, 'days' => $days];
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** Resolve pending clones against the active enrollment, never their own schedule alone. */
    private function scheduledFor($order, $date): bool
    {
        $source = $order;
        if (!empty($order->uddds_source_docointkey)) {
            $source = DB::selectOne('SELECT * FROM hospital.dbo.hrxo WHERE docointkey = ?', [$order->uddds_source_docointkey]);
            if (!$source || !$source->is_uddds || !empty($source->uddds_source_docointkey)) {
                return false;
            }
            if (UdddsSchedule::date($order->dodate)->format('Y-m-d') !== UdddsSchedule::date($date)->format('Y-m-d')
                || UdddsSchedule::date($order->uddds_start_date) != UdddsSchedule::date($source->uddds_start_date)
                || UdddsSchedule::date($order->uddds_end_date) != UdddsSchedule::date($source->uddds_end_date)
                || UdddsSchedule::interval($order->uddds_interval_days ?? null) !== UdddsSchedule::interval($source->uddds_interval_days ?? null)) {
                return false;
            }
        }
        return !empty($source->is_uddds) && $source->order_type === 'BASIC'
            && UdddsSchedule::due($source->uddds_start_date, $source->uddds_end_date, $source->uddds_interval_days ?? null, $date);
    }

    private function decorateSchedule($item, $date): void
    {
        // An eligible standing order can still carry an earlier day's charge slip.
        $item->selected_date_charge_code = $this->reprintChargeCodes([$item], $date)[0] ?? null;
        try {
            $item->schedule_label = UdddsSchedule::label($item->uddds_interval_days ?? null);
            $item->next_supply_date = UdddsSchedule::next($item->uddds_start_date, $item->uddds_end_date, $item->uddds_interval_days ?? null, $date);
            $item->schedule_error = null;
        } catch (\InvalidArgumentException $e) {
            $item->schedule_label = 'Invalid schedule';
            $item->next_supply_date = null;
            $item->schedule_error = $e->getMessage();
            $item->is_actionable = 0;
        }
    }

    public function generatedOrderProblem(array $keys): ?string
    {
        if (!$keys || !self::hasHrxoColumns()) return null;
        $keys = array_map(fn ($key) => trim((string) $key, "'\""), $keys);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $rows = DB::select("SELECT * FROM hospital.dbo.hrxo WHERE docointkey IN ({$placeholders})", $keys);
        foreach ($rows as $row) {
            if (empty($row->uddds_source_docointkey)) continue;
            try {
                if (!$row->is_uddds || !$this->scheduledFor($row, $row->dodate)) {
                    return 'Generated UDDDS order is off schedule or its enrollment changed. Reload the queue.';
                }
            } catch (\InvalidArgumentException $e) { return $e->getMessage(); }
        }
        return null;
    }

    private function validateProcessingSchedule(array $keys): ?string
    {
        if (!$keys) return null;
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $orders = DB::select("SELECT * FROM hospital.dbo.hrxo WHERE docointkey IN ({$placeholders})", $keys);
        if (count($orders) !== count(array_unique($keys))) return 'One or more UDDDS orders no longer exist. Reload the queue.';
        foreach ($orders as $order) {
            try {
                if (!$order->is_uddds || !$this->scheduledFor($order, $order->dodate)) {
                    return 'Selected UDDDS order is off schedule or its enrollment changed. Reload the queue.';
                }
            } catch (\InvalidArgumentException $e) {
                return $e->getMessage();
            }
        }
        return null;
    }

    public function newOrderUdddsFields($orderType, $startDate = null, $endDate = null, $intervalDays = 1): array
    {
        $type = strtoupper(trim((string) $orderType));
        if (!in_array($type, ['BASIC', 'G24', 'OR'], true)) {
            return ['ok' => false, 'field' => 'rx_order_type', 'message' => 'Choose Basic (standing), G24 or OR Use.'];
        }
        $hasDates = !empty($startDate) || !empty($endDate);
        if ($type !== 'BASIC' && $hasDates) {
            return ['ok' => false, 'field' => 'rx_order_type', 'message' => 'UDDDS recurrence applies to Basic (standing) orders only.'];
        }
        if (!self::hasHrxoColumns()) {
            return $hasDates ? ['ok' => false, 'message' => $this->schemaMissingMessage()] : ['ok' => true, 'fields' => []];
        }
        $fields = ['order_type' => $type, 'is_uddds' => false, 'uddds_start_date' => null, 'uddds_end_date' => null];
        if ($hasDates) {
            $schedule = $this->enrollmentSchedule($startDate, $endDate, $intervalDays);
            if (!$schedule['ok']) return $schedule;
            $fields['uddds_start_date'] = UdddsSchedule::date($startDate)->format('Y-m-d');
            $fields['uddds_end_date'] = UdddsSchedule::date($endDate)->format('Y-m-d');
            if (self::hasIntervalColumn()) $fields['uddds_interval_days'] = $schedule['days'];
        } elseif (self::hasIntervalColumn()) {
            $fields['uddds_interval_days'] = null;
        }
        return ['ok' => true, 'fields' => $fields];
    }

    public function normalizeOrderType($type)
    {
        $type = strtoupper(trim((string) $type));

        if ($type === 'G24') {
            return 'G24';
        }

        if ($type === 'OR') {
            return 'OR';
        }

        return 'BASIC';
    }

    public function isBasic($type)
    {
        return $this->normalizeOrderType($type) === 'BASIC';
    }

    public function isAdmEncounter($enccode): bool
    {
        if (!$enccode) {
            return false;
        }

        $row = DB::selectOne(
            'SELECT TOP 1 toecode FROM hospital.dbo.henctr WHERE enccode = ?',
            [$enccode]
        );

        return $row && strtoupper(trim((string) $row->toecode)) === 'ADM';
    }

    public function enrollIssuedOrders(array $docointkeys, $startDate, $endDate, $intervalDays = 1)
    {
        $schedule = $this->enrollmentSchedule($startDate, $endDate, $intervalDays);
        if (!$schedule['ok']) return $schedule;
        $intervalDays = $schedule['days'];
        $intervalSql = self::hasIntervalColumn() ? ', uddds_interval_days = ?' : '';

        $start = UdddsSchedule::date($startDate)->format('Y-m-d');
        $end = UdddsSchedule::date($endDate)->format('Y-m-d');

        if ($end < $start) {
            return ['ok' => false, 'message' => 'End date must be on or after the start date.'];
        }

        foreach ($docointkeys as $key) {
            $candidate = DrugOrder::where('docointkey', $key)->first();
            if ($candidate && ($candidate->is_uddds || !empty($candidate->uddds_source_docointkey))) {
                return ['ok' => false, 'message' => 'Remove the active UDDDS enrollment before enrolling again.'];
            }
        }

        foreach ($docointkeys as $docointkey) {
            $order = DrugOrder::where('docointkey', $docointkey)->first();

            if (!$order || !$this->isBasic($order->order_type) || !$this->isAdmEncounter($order->enccode)) {
                continue;
            }

            if ($order->is_uddds || !empty($order->uddds_source_docointkey)) {
                return ['ok' => false, 'message' => 'Remove the active UDDDS enrollment before enrolling again.'];
            }

            DB::update(
                "UPDATE hospital.dbo.hrxo
                    SET is_uddds = 1,
                        uddds_start_date = ?,
                        uddds_end_date = ?,
                        order_type = 'BASIC',
                        uddds_source_docointkey = NULL{$intervalSql}
                    WHERE docointkey = ?",
                array_merge([$start, $end], self::hasIntervalColumn() ? [$intervalDays] : [], [$docointkey])
            );
        }

        return ['ok' => true, 'message' => 'UDDDS enrollment saved.'];
    }

    public function activateOnIssued(array $docointkeys)
    {
        if (!self::hasHrxoColumns()) {
            return ['ok' => true, 'message' => ''];
        }

        foreach ($docointkeys as $docointkey) {
            $order = DrugOrder::where('docointkey', $docointkey)->first();

            if (!$order || !$this->isBasic($order->order_type) || !$this->isAdmEncounter($order->enccode)) {
                continue;
            }

            if (!empty($order->uddds_source_docointkey)) continue;
            if (!$order->uddds_start_date || !$order->uddds_end_date) {
                continue;
            }

            $schedule = $this->enrollmentSchedule($order->uddds_start_date, $order->uddds_end_date, $order->uddds_interval_days ?? null);
            if (!$schedule['ok']) return $schedule;

            DB::update(
                "UPDATE hospital.dbo.hrxo
                    SET is_uddds = 1,
                        uddds_source_docointkey = NULL
                    WHERE docointkey = ?",
                [$docointkey]
            );
        }

        return ['ok' => true, 'message' => 'UDDDS activated for issued standing items.'];
    }

    public function enrollSingleOrder($docointkey, $orderType, $startDate, $endDate, $intervalDays = 1)
    {
        $schedule = $this->enrollmentSchedule($startDate, $endDate, $intervalDays);
        if (!$schedule['ok']) return $schedule;
        $intervalDays = $schedule['days'];
        $intervalSql = self::hasIntervalColumn() ? ', uddds_interval_days = ?' : '';

        if (!self::hasHrxoColumns()) {
            return ['ok' => false, 'message' => $this->schemaMissingMessage()];
        }

        $order = DrugOrder::where('docointkey', $docointkey)->first();

        if (!$order) {
            return ['ok' => false, 'message' => 'Order not found.'];
        }

        if (!$this->isAdmEncounter($order->enccode)) {
            return ['ok' => false, 'message' => 'UDDDS is only available for inpatient (ADM) encounters.'];
        }

        if (!empty($order->uddds_source_docointkey)) {
            return ['ok' => false, 'message' => 'This row was generated from a standing UDDDS order.'];
        }

        if (!$this->isBasic($order->order_type)) {
            return ['ok' => false, 'message' => 'UDDDS applies to Basic (standing) orders only.'];
        }
        if ($order->prescription_data_id) {
            $prescription = \App\Models\Record\Prescriptions\PrescriptionData::find($order->prescription_data_id);
            if ($prescription && !$this->isBasic($prescription->order_type)) {
                return ['ok' => false, 'message' => 'UDDDS applies to Basic (standing) prescriptions only.'];
            }
        }

        if ($order->is_uddds) return ['ok' => false, 'message' => 'Remove the active UDDDS enrollment before enrolling again.'];

        $type = $this->normalizeOrderType($orderType);

        if ($type !== 'BASIC') {
            return ['ok' => false, 'message' => 'UDDDS applies to Basic (standing) orders only. Choose Basic, then set start and end dates.'];
        }

        if (!$startDate || !$endDate) {
            return ['ok' => false, 'message' => 'UDDDS start and end dates are required.'];
        }

        try {
            $start = UdddsSchedule::date($startDate)->format('Y-m-d');
            $end = UdddsSchedule::date($endDate)->format('Y-m-d');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'UDDDS start and end dates are required.'];
        }

        if ($end < $start) {
            return ['ok' => false, 'message' => 'End date must be on or after the start date.'];
        }

        DB::update(
            "UPDATE hospital.dbo.hrxo
                SET is_uddds = 1,
                    uddds_start_date = ?,
                    uddds_end_date = ?,
                    order_type = 'BASIC',
                    uddds_source_docointkey = NULL{$intervalSql}
                WHERE docointkey = ?",
            array_merge([$start, $end], self::hasIntervalColumn() ? [$intervalDays] : [], [$docointkey])
        );

        return ['ok' => true, 'message' => 'UDDDS enabled. Unit-dose orders will generate on scheduled supply dates through the end date.'];
    }

    public function removeFromUddds($docointkey)
    {
        $order = DrugOrder::where('docointkey', $docointkey)->first();

        if (!$order) {
            return ['ok' => false, 'message' => 'Order not found.'];
        }

        $sourceKey = $order->uddds_source_docointkey ?: $order->docointkey;

        DB::update(
            "UPDATE hospital.dbo.hrxo SET is_uddds = 0 WHERE docointkey = ? OR uddds_source_docointkey = ?",
            [$sourceKey, $sourceKey]
        );

        return ['ok' => true, 'message' => 'Item removed from UDDDS. Already charged or issued rows were kept.'];
    }

    public function generateDaily($referenceDate = null, $dryRun = false)
    {
        $today = UdddsSchedule::date($referenceDate ?: now('Asia/Manila'))->format('Y-m-d');

        $enrollments = DB::select("
            SELECT hrxo.*
            FROM hospital.dbo.hrxo
            INNER JOIN hospital.dbo.henctr enctr ON enctr.enccode = hrxo.enccode
            INNER JOIN hospital.dbo.hpatroom pat_room ON pat_room.enccode = hrxo.enccode AND pat_room.patrmstat = 'A'
            WHERE hrxo.is_uddds = 1
                AND (hrxo.uddds_source_docointkey IS NULL OR hrxo.uddds_source_docointkey = '')
                AND hrxo.estatus = 'S'
                AND hrxo.uddds_start_date IS NOT NULL
                AND hrxo.uddds_end_date IS NOT NULL
                AND CAST(hrxo.uddds_start_date AS DATE) <= ?
                AND CAST(hrxo.uddds_end_date AS DATE) >= ?
                AND (enctr.toecode = 'ADM' OR enctr.toecode = 'OPDAD' OR enctr.toecode = 'ERADM')
        ", [$today, $today]);

        foreach ($enrollments as $enrollment) $this->scheduledFor($enrollment, $today);

        $created = [];
        $skipped = 0;

        foreach ($enrollments as $index => $enrollment) {
            if (!$this->scheduledFor($enrollment, $today)) {
                $skipped++;
                continue;
            }
            $issuedOn = Carbon::parse($enrollment->dodate)->toDateString();

            if ($issuedOn === $today) {
                $skipped++;
                continue;
            }

            $existing = DB::selectOne("
                SELECT TOP 1 docointkey
                FROM hospital.dbo.hrxo
                WHERE uddds_source_docointkey = ?
                    AND CAST(dodate AS DATE) = ?
            ", [$enrollment->docointkey, $today]);

            if ($existing) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $created[] = [
                    'source' => $enrollment->docointkey,
                    'enccode' => $enrollment->enccode,
                    'dmdcomb' => $enrollment->dmdcomb,
                    'dmdctr' => $enrollment->dmdctr,
                ];
                continue;
            }

            $created[] = $this->cloneEnrollmentForDate($enrollment, $today, $index);
        }

        return [
            'run_at' => now('Asia/Manila')->toDateTimeString(),
            'date' => $today,
            'dry_run' => $dryRun,
            'count' => count($created),
            'skipped' => $skipped,
            'items' => $created,
        ];
    }

    public function todaysWardItems($wardcode, $locationId)
    {
        return $this->wardItemsForDate($wardcode, $locationId, now('Asia/Manila')->toDateString());
    }

    public function wardItemsForDate($wardcode, $locationId, $referenceDate)
    {
        if (!self::hasHrxoColumns()) {
            return [];
        }
        $drugDescription = DrugDescription::fromMaster('hdmhdr');
        $intervalSelect = $this->intervalSelect();
        $today = UdddsSchedule::date($referenceDate ?: now('Asia/Manila'))->format('Y-m-d');
        $nextDay = Carbon::parse($today)->addDay()->toDateString();
        $params = [];
        $wardFilter = '';

        if ($wardcode) {
            $wardFilter = ' AND ward.wardcode = ?';
            $params[] = $wardcode;
        }

        $items = DB::select("
            SELECT
                hrxo.docointkey,
                hrxo.enccode,
                hrxo.hpercode,
                hrxo.dmdcomb,
                hrxo.dmdctr,
                hrxo.orderfrom,
                hrxo.pchrgqty,
                hrxo.pchrgup,
                hrxo.pcchrgamt,
                hrxo.estatus,
                hrxo.qtyissued,
                hrxo.dodate,
                hrxo.pcchrgcod,
                hrxo.loc_code,
                {$intervalSelect},
                hrxo.uddds_start_date,
                hrxo.uddds_end_date,
                hrxo.uddds_source_docointkey,
                hrxo.order_type,
                hrxo.is_uddds,
                CASE
                    WHEN hrxo.uddds_source_docointkey IS NOT NULL
                        AND hrxo.estatus IN ('U', 'P')
                        AND (hrxo.qtyissued IS NULL OR hrxo.qtyissued = 0)
                    THEN 1 ELSE 0
                END AS is_billable,
                CASE
                    WHEN hrxo.uddds_source_docointkey IS NULL OR hrxo.uddds_source_docointkey = '' THEN 1
                    WHEN hrxo.estatus IN ('U', 'P') AND (hrxo.qtyissued IS NULL OR hrxo.qtyissued = 0) THEN 1
                    ELSE 0
                END AS is_actionable,
                {$drugDescription} AS drug_concat,
                hcharge.chrgdesc,
                pt.patfirst,
                pt.patmiddle,
                pt.patlast,
                pt.patsuffix,
                ward.wardcode,
                ward.wardname,
                room.rmname,
                pd.remark AS frequency,
                pd.addtl_remarks
            FROM hospital.dbo.hrxo
            INNER JOIN hospital.dbo.hdmhdr ON hdmhdr.dmdcomb = hrxo.dmdcomb AND hdmhdr.dmdctr = hrxo.dmdctr
            INNER JOIN hospital.dbo.hcharge ON hcharge.chrgcode = hrxo.orderfrom
            INNER JOIN hospital.dbo.hperson pt ON pt.hpercode = hrxo.hpercode
            INNER JOIN hospital.dbo.henctr enctr ON enctr.enccode = hrxo.enccode AND enctr.toecode = 'ADM'
            INNER JOIN hospital.dbo.hpatroom pat_room ON pat_room.enccode = hrxo.enccode AND pat_room.patrmstat = 'A'
            INNER JOIN hospital.dbo.hward ward ON ward.wardcode = pat_room.wardcode
            LEFT JOIN hospital.dbo.hroom room ON room.rmintkey = pat_room.rmintkey
            LEFT JOIN webapp.dbo.prescription_data pd ON pd.id = hrxo.prescription_data_id
            LEFT JOIN (
                SELECT DISTINCT uddds_source_docointkey
                FROM hospital.dbo.hrxo
                WHERE dodate >= ? AND dodate < ?
                    AND uddds_source_docointkey IS NOT NULL
            ) daily_orders ON daily_orders.uddds_source_docointkey = hrxo.docointkey
            WHERE hrxo.is_uddds = 1
                AND (
                    (
                        hrxo.uddds_source_docointkey IS NOT NULL
                        AND hrxo.dodate >= ? AND hrxo.dodate < ?
                        AND hrxo.estatus IN ('U', 'P', 'S')
                    )
                    OR
                    (
                        (hrxo.uddds_source_docointkey IS NULL OR hrxo.uddds_source_docointkey = '')
                        AND hrxo.estatus = 'S'
                        AND hrxo.order_type = 'BASIC'
                        AND hrxo.uddds_start_date < ?
                        AND hrxo.uddds_end_date >= ?
                        AND daily_orders.uddds_source_docointkey IS NULL
                    )
                )
                AND (hrxo.loc_code = ? OR hrxo.loc_code IS NULL)
                {$wardFilter}
            ORDER BY ward.wardname, pt.patlast, pt.patfirst, {$drugDescription}
        ", [$today, $nextDay, $today, $nextDay, $nextDay, $today, $locationId, ...$params]);

        foreach ($items as $key => $item) {
                $this->decorateSchedule($item, $today);
                $historical = $item->estatus === 'S' || (float) $item->qtyissued > 0;
                try {
                    if (!$historical && !$item->schedule_error && !$this->scheduledFor($item, $today)) {
                        unset($items[$key]);
                        continue;
                    }
                    if ($historical && empty($item->uddds_source_docointkey) && !$item->schedule_error && !$this->scheduledFor($item, $today)) {
                        unset($items[$key]);
                        continue;
                    }
                } catch (\InvalidArgumentException $e) {
                    $item->schedule_error = $e->getMessage();
                    $item->is_actionable = 0;
                }

            $item->is_source_issued_for_date = empty($item->uddds_source_docointkey)
                && Carbon::parse($item->dodate)->toDateString() === $today
                && ($item->estatus === 'S' || (float) $item->qtyissued > 0);

            if ($item->is_source_issued_for_date) {
                $item->is_actionable = 0;
            }
        }

        return array_values($items);
    }

    public function processedWardItemsForDate($wardcode, $locationId, $referenceDate): array
    {
        if (!self::hasHrxoColumns()) {
            return [];
        }
        $drugDescription = DrugDescription::fromMaster('hdmhdr');
        $date = UdddsSchedule::date($referenceDate)->format('Y-m-d');
        $nextDay = Carbon::parse($date)->addDay()->toDateString();
        $params = [$nextDay, $date, $nextDay, $locationId];
        $wardFilter = '';
        if ($wardcode) {
            $wardFilter = ' AND pat_room.wardcode = ?';
            $params[] = $wardcode;
        }

        $items = DB::select("
            SELECT hrxo.*, {$drugDescription} AS drug_concat, hcharge.chrgdesc,
                pt.patfirst, pt.patmiddle, pt.patlast, pt.patsuffix,
                ward.wardname, room.rmname, pd.remark AS frequency,
                0 AS is_billable, 0 AS is_actionable,
                CASE WHEN hrxo.estatus = 'S' OR hrxo.qtyissued > 0 THEN 1 ELSE 0 END AS is_source_issued_for_date
            FROM hospital.dbo.hrxo
            OUTER APPLY (
                SELECT TOP 1 wardcode, rmintkey
                FROM hospital.dbo.hpatroom
                WHERE enccode = hrxo.enccode AND hprdate < ?
                ORDER BY hprdate DESC, wardcode, rmintkey
            ) pat_room
            LEFT JOIN hospital.dbo.hward ward ON ward.wardcode = pat_room.wardcode
            LEFT JOIN hospital.dbo.hroom room ON room.rmintkey = pat_room.rmintkey
            LEFT JOIN hospital.dbo.hperson pt ON pt.hpercode = hrxo.hpercode
            LEFT JOIN hospital.dbo.hdmhdr ON hdmhdr.dmdcomb = hrxo.dmdcomb AND hdmhdr.dmdctr = hrxo.dmdctr
            LEFT JOIN hospital.dbo.hcharge ON hcharge.chrgcode = hrxo.orderfrom
            LEFT JOIN webapp.dbo.prescription_data pd ON pd.id = hrxo.prescription_data_id
            WHERE hrxo.dodate >= ? AND hrxo.dodate < ?
                AND hrxo.pcchrgcod IS NOT NULL AND hrxo.pcchrgcod <> ''
                AND (hrxo.is_uddds = 1
                    OR (hrxo.uddds_source_docointkey IS NOT NULL AND hrxo.uddds_source_docointkey <> '')
                    OR (hrxo.order_type = 'BASIC' AND hrxo.uddds_start_date IS NOT NULL AND hrxo.uddds_end_date IS NOT NULL))
                AND (hrxo.loc_code = ? OR hrxo.loc_code IS NULL)
                {$wardFilter}
            ORDER BY ward.wardname, pt.patlast, pt.patfirst, hrxo.pcchrgcod
        ", $params);
        foreach ($items as $item) $this->decorateSchedule($item, $date);
        return $items;
    }

    public function selectedPatientChargeCodes(array $items, array $encounters, $referenceDate): array
    {
        $selected = array_values(array_filter($items, fn ($item) => in_array((string) $item->enccode, $encounters, true)));
        return $this->reprintChargeCodes($selected, $referenceDate);
    }

    public function reprintChargeCodes(array $items, $referenceDate): array
    {
        $date = UdddsSchedule::date($referenceDate)->format('Y-m-d');
        $codes = [];

        foreach ($items as $item) {
            $code = trim((string) ($item->pcchrgcod ?? ''));
            // Standing enrollments may carry a slip from an earlier service date.
            if ($code === '' || empty($item->dodate)
                || Carbon::parse($item->dodate)->toDateString() !== $date) {
                continue;
            }

            $codes[] = $code;
        }

        return array_values(array_unique($codes));
    }

    public function filterWardItemsByStatus(array $items, $status = 'all', $processed = false): array
    {
        foreach ($items as $item) {
            if (!empty($item->is_source_issued_for_date)) {
                $item->queue_status = 'issued';
            } elseif (!$processed && empty($item->uddds_source_docointkey)) {
                $item->queue_status = 'eligible';
            } elseif ($item->estatus === 'S' || (float) $item->qtyissued > 0) {
                $item->queue_status = 'issued';
            } elseif ($item->estatus === 'U' || empty($item->pcchrgcod)) {
                $item->queue_status = 'pending';
            } else {
                $item->queue_status = 'charged';
            }
        }
        if (!in_array($status, ['pending', 'charged', 'issued', 'eligible'], true)) return $items;
        return array_values(array_filter($items, fn ($item) => $item->queue_status === $status));
    }

    public function uncoveredFundGroups(array $groups, array $fallbacks): array
    {
        $uncovered = [];
        foreach ($groups as $key => $group) {
            $item = $group['item'];
            $coverage = (float) $item->current_available;
            $chosen = array_values(array_unique(array_filter((array) ($fallbacks[$key] ?? []))));
            foreach ($item->alternate_funds ?? [] as $option) {
                if (in_array($option['code'], $chosen, true)) $coverage += $option['available'];
            }
            if ($coverage + 0.000001 < $group['qty']) $uncovered[] = $key;
        }
        return $uncovered;
    }

    public function fundSelectionGroups(array $items, array $selectedKeys): array
    {
        $groups = [];
        foreach ($items as $item) {
            if (empty($item->is_actionable) || !in_array((string) $item->docointkey, $selectedKeys, true)) continue;
            $key = UdddsStockAllocator::groupKey($item);
            if (!isset($groups[$key])) $groups[$key] = ['item' => $item, 'qty' => 0, 'patients' => []];
            $groups[$key]['qty'] += (float) $item->pchrgqty;
            $groups[$key]['patients'][$item->enccode] = true;
        }
        return $groups;
    }

    public function annotatePendingStock(array $items, $locationId): array
    {
        $groups = [];
        foreach ($items as $item) {
            if (empty($item->is_actionable)) continue;
            $key = $item->dmdcomb . '|' . $item->dmdctr . '|' . $item->orderfrom;
            if (!isset($groups[$key])) $groups[$key] = ['item' => $item, 'needed' => 0];
            $groups[$key]['needed'] += (float) $item->pchrgqty;
        }
        $available = [];
        $fundOptions = [];
        foreach (array_chunk($groups, 100, true) as $chunk) {
            $stocks = DrugStock::query()->where('loc_code', $locationId)
                ->where('exp_date', '>', now()->toDateString())->where('stock_bal', '>', 0)
                ->whereIn('chrgcode', app('chargetable'))->with('charge')
                ->where(function ($query) use ($chunk) {
                    foreach ($chunk as $group) {
                        $item = $group['item'];
                        $query->orWhere(function ($match) use ($item) {
                            $match->where('dmdcomb', $item->dmdcomb)->where('dmdctr', $item->dmdctr);
                        });
                    }
                })->selectRaw('dmdcomb, dmdctr, chrgcode, SUM(stock_bal) AS available_qty')
                ->groupBy('dmdcomb', 'dmdctr', 'chrgcode')->get();
            foreach ($stocks as $stock) {
                $available[$stock->dmdcomb . '|' . $stock->dmdctr . '|' . $stock->chrgcode] = (float) $stock->available_qty;
                $fundOptions[$stock->dmdcomb . '|' . $stock->dmdctr][$stock->chrgcode] = [
                    'code' => $stock->chrgcode,
                    'name' => optional($stock->charge)->chrgdesc ?: $stock->chrgcode,
                    'available' => (float) $stock->available_qty,
                ];
            }
        }
        foreach ($items as $item) {
            $item->stock_problem = false;
            $item->pending_reason = null;
            $item->alternate_funds = [];
            $item->fallback_key = UdddsStockAllocator::groupKey($item);
            if (empty($item->is_actionable)) continue;
            $key = $item->dmdcomb . '|' . $item->dmdctr . '|' . $item->orderfrom;
            $balance = $available[$key] ?? 0;
            $needed = $groups[$key]['needed'];
            $item->current_available = $balance;
            $item->queue_needed = $needed;
            $item->stock_problem = $balance < $needed;
            // A standing enrollment's slip belongs to its original dose. The next
            // daily order is materialized without a charge before allocation.
            $hasDailyCharge = !empty($item->uddds_source_docointkey) && !empty($item->pcchrgcod);
            if ($item->stock_problem && !$hasDailyCharge) {
                foreach ($fundOptions[$item->dmdcomb . '|' . $item->dmdctr] ?? [] as $option) {
                    if ($option['code'] !== $item->orderfrom) $item->alternate_funds[] = $option;
                }
            }
            $item->pending_reason = $item->stock_problem
                ? 'Stock shortage for this queue: need ' . $needed . ', available ' . $balance . ' (same drug and fund source).'
                : 'Stock available. Awaiting charge/issue processing.';
        }
        return $items;
    }

    public function validateFefoStock(array $items, $locationId)
    {
        $needed = [];

        foreach ($items as $item) {
            $key = $item->dmdcomb . '|' . $item->dmdctr . '|' . $item->orderfrom;
            if (!isset($needed[$key])) {
                $needed[$key] = [
                    'dmdcomb' => $item->dmdcomb,
                    'dmdctr' => $item->dmdctr,
                    'chrgcode' => $item->orderfrom,
                    'qty' => 0,
                    'label' => $item->drug_concat ?? ($item->dmdcomb . '-' . $item->dmdctr),
                ];
            }
            $needed[$key]['qty'] += (float) $item->pchrgqty;
        }

        $shortages = [];

        foreach ($needed as $group) {
            $available = (float) DrugStock::where('dmdcomb', $group['dmdcomb'])
                ->where('dmdctr', $group['dmdctr'])
                ->where('chrgcode', $group['chrgcode'])
                ->where('loc_code', $locationId)
                ->where('exp_date', '>', now()->toDateString())
                ->where('stock_bal', '>', 0)
                ->sum('stock_bal');

            if ($available < $group['qty']) {
                $shortages[] = $group['label'] . ' (need ' . $group['qty'] . ', available ' . $available . ')';
            }
        }

        if ($shortages) {
            return [
                'ok' => false,
                'message' => 'Insufficient stock: ' . implode('; ', $shortages),
            ];
        }

        return ['ok' => true, 'message' => 'Stock available.'];
    }

    public function materializeDailyItems(array $docointkeys, $referenceDate): array
    {
        $docointkeys = array_values(array_filter($docointkeys));
        if (!$docointkeys) {
            return [];
        }

        $date = UdddsSchedule::date($referenceDate ?: now('Asia/Manila'))->format('Y-m-d');
        $placeholders = implode(',', array_fill(0, count($docointkeys), '?'));
        $orders = DB::select(
            "SELECT hrxo.*
             FROM hospital.dbo.hrxo
             WHERE hrxo.docointkey IN ({$placeholders})
               AND hrxo.is_uddds = 1",
            $docointkeys
        );

        if (count($orders) !== count(array_unique($docointkeys))) {
            throw new \InvalidArgumentException('One or more selected UDDDS orders are no longer active. Reload the queue.');
        }
        foreach ($orders as $order) {
            if (!$this->scheduledFor($order, $date)) {
                throw new \InvalidArgumentException('Selected UDDDS order is off schedule or its enrollment changed. Reload the queue.');
            }
        }
        $dailyKeys = [];
        foreach ($orders as $index => $order) {
            if (!empty($order->uddds_source_docointkey)) {
                $dailyKeys[] = $order->docointkey;
                continue;
            }

            if (Carbon::parse($order->dodate)->toDateString() === $date
                && ($order->estatus === 'S' || (float) $order->qtyissued > 0)) {
                continue;
            }

            $existing = DB::selectOne(
                "SELECT TOP 1 docointkey
                 FROM hospital.dbo.hrxo
                 WHERE uddds_source_docointkey = ?
                   AND CAST(dodate AS DATE) = ?",
                [$order->docointkey, $date]
            );

            if ($existing) {
                $dailyKeys[] = $existing->docointkey;
                continue;
            }

            if ($order->estatus === 'S'
                && $order->order_type === 'BASIC'
                && Carbon::parse($order->uddds_start_date)->toDateString() <= $date
                && Carbon::parse($order->uddds_end_date)->toDateString() >= $date) {
                $dailyKeys[] = $this->cloneEnrollmentForDate($order, $date, $index)['docointkey'];
            }
        }

        return array_values(array_unique($dailyKeys));
    }

    public function chargeAndIssue(array $docointkeys, $locationId, array $actor, array $fallbacks = [])
    {
        $drugDescription = DrugDescription::fromMaster('hdmhdr');
        $docointkeys = array_values(array_filter($docointkeys));

        if (empty($docointkeys)) {
            return ['ok' => false, 'message' => 'No UDDDS items selected.', 'pcchrgcods' => []];
        }

        if ($problem = $this->validateProcessingSchedule($docointkeys)) {
            return ['ok' => false, 'message' => $problem, 'pcchrgcods' => []];
        }

        if (array_filter($fallbacks)) {
            return $this->chargeAndIssueWithFallback(array_values(array_unique($docointkeys)), $locationId, $actor, $fallbacks);
        }

        $placeholders = implode(',', array_fill(0, count($docointkeys), '?'));
        $items = DB::select(
            "SELECT hrxo.*, {$drugDescription} AS drug_concat
             FROM hospital.dbo.hrxo
             INNER JOIN hospital.dbo.hdmhdr ON hdmhdr.dmdcomb = hrxo.dmdcomb AND hdmhdr.dmdctr = hrxo.dmdctr
             INNER JOIN hospital.dbo.henctr enctr ON enctr.enccode = hrxo.enccode AND enctr.toecode = 'ADM'
             WHERE hrxo.docointkey IN ({$placeholders})
                AND hrxo.is_uddds = 1
                AND (hrxo.estatus = 'U' OR (hrxo.estatus = 'P' AND (hrxo.qtyissued IS NULL OR hrxo.qtyissued = 0)))",
            $docointkeys
        );

        if (!$items) {
            return ['ok' => false, 'message' => 'No billable UDDDS items found.', 'pcchrgcods' => []];
        }

        $stockCheck = $this->validateFefoStock($items, $locationId);
        if (!$stockCheck['ok']) {
            return array_merge($stockCheck, ['pcchrgcods' => []]);
        }

        $byEncounter = [];
        foreach ($items as $item) {
            $byEncounter[$item->enccode][] = $item;
        }

        $pcchrgcods = [];

        foreach ($byEncounter as $encounterItems) {
            $pending = array_filter($encounterItems, function ($item) {
                return $item->estatus === 'U' || empty($item->pcchrgcod);
            });

            $pcchrgcod = null;
            if ($pending) {
                $chargeCode = OrderChargeCode::create(['charge_desc' => 'a']);
                $pcchrgcod = 'P' . date('y') . '-' . sprintf('%07d', $chargeCode->id);

                foreach ($pending as $item) {
                    DB::update(
                        "UPDATE hospital.dbo.hrxo
                            SET pcchrgcod = ?, estatus = 'P'
                            WHERE docointkey = ?
                              AND ((estatus = 'U' OR orderfrom = 'DRUMK' OR pchrgup = 0) AND pcchrgcod IS NULL)",
                        [$pcchrgcod, $item->docointkey]
                    );
                    $item->pcchrgcod = $pcchrgcod;
                    $item->estatus = 'P';
                }
            }

            // Include every existing and newly assigned slip, even on partial issuance.
            foreach ($encounterItems as $item) {
                if (!empty($item->pcchrgcod)) {
                    $pcchrgcods[] = $item->pcchrgcod;
                }
            }
            $pcchrgcods = array_values(array_unique($pcchrgcods));

            foreach ($encounterItems as $item) {
                $issued = $this->issueOne($item, $locationId, $actor);
                if (!$issued['ok']) {
                    return ['ok' => false, 'message' => $issued['message'], 'pcchrgcods' => $pcchrgcods];
                }
            }

        }

        return [
            'ok' => true,
            'message' => 'Batch charge and issuance completed.',
            'pcchrgcods' => array_values(array_unique($pcchrgcods)),
        ];
    }

    private function chargeAndIssueWithFallback(array $keys, $locationId, array $actor, array $fallbacks): array
    {
        $allowedFunds = app('chargetable');
        foreach ($fallbacks as $key => $funds) {
            $fallbacks[$key] = array_values(array_unique(array_filter((array) $funds)));
            foreach ($fallbacks[$key] as $fund) {
                if (!is_string($fund) || !in_array($fund, $allowedFunds, true)) {
                    return ['ok' => false, 'message' => 'Invalid alternate fund source.', 'pcchrgcods' => []];
                }
            }
        }
        if (count($keys) > 500) return ['ok' => false, 'message' => 'Process at most 500 items at a time when using alternate funds.', 'pcchrgcods' => []];

        try {
            return DB::connection('hospital')->transaction(function () use ($keys, $locationId, $actor, $fallbacks, $allowedFunds) {
                $orders = DrugOrder::whereIn('docointkey', $keys)->where('is_uddds', 1)
                    ->whereHas('enctr', function ($query) { $query->where('toecode', 'ADM'); })
                    ->where(function ($query) use ($locationId) { $query->where('loc_code', $locationId)->orWhereNull('loc_code'); })
                    ->whereIn('estatus', ['U', 'P'])->where(function ($query) { $query->whereNull('qtyissued')->orWhere('qtyissued', 0); })
                    ->orderBy('docointkey')->lockForUpdate()->get();
                if ($orders->count() !== count($keys)) return ['ok' => false, 'message' => 'Some selected orders changed or are already issued. Refresh the queue and select again.', 'pcchrgcods' => []];
                foreach ($orders as $order) {
                    if (empty($order->uddds_source_docointkey)) return ['ok' => false, 'message' => 'Only generated daily UDDDS orders can use alternate funds.', 'pcchrgcods' => []];
                }
                $stocks = DB::connection('hospital')->table('hospital.dbo.pharm_drug_stocks as stock')
                    ->join('hospital.dbo.hdmhdrprice as price', 'price.dmdprdte', '=', 'stock.dmdprdte')
                    ->where('stock.loc_code', $locationId)->where('stock.exp_date', '>', now()->toDateString())
                    ->where('stock.stock_bal', '>', 0)->whereIn('stock.chrgcode', $allowedFunds)
                    ->whereNotNull('price.retail_price')->where('price.retail_price', '>=', 0)
                    ->where(function ($query) use ($orders) {
                        foreach ($orders as $order) {
                            $query->orWhere(function ($match) use ($order) {
                                $match->where('stock.dmdcomb', $order->dmdcomb)->where('stock.dmdctr', $order->dmdctr);
                            });
                        }
                    })->select('stock.*', 'price.dmduprice', 'price.retail_price as fund_unit_price')
                    ->orderBy('stock.exp_date')->orderBy('stock.id')->lockForUpdate()->get()->all();
                $plan = (new UdddsStockAllocator())->allocate($orders->all(), $stocks, $fallbacks);
                if (!$plan['ok']) return ['ok' => false, 'message' => $plan['message'], 'pcchrgcods' => []];
                foreach ($orders as $order) {
                    if (!empty($order->pcchrgcod) && count($plan['plans'][$order->docointkey]) > 1) {
                        return ['ok' => false, 'message' => 'An existing charge would need to be split. Process already-charged items separately.', 'pcchrgcods' => []];
                    }
                }
                // All stock is reserved and validated before any charges or deductions.
                $slips = [];
                $stockRemaining = [];
                foreach ($stocks as $stock) $stockRemaining[$stock->id] = (float) $stock->stock_bal;
                $codes = [];
                foreach ($orders as $order) {
                    $parts = $plan['plans'][$order->docointkey];
                    $template = $order->getAttributes();
                    $code = $order->pcchrgcod;
                    if (!$code) {
                        if (!isset($slips[$order->enccode])) {
                            $charge = OrderChargeCode::create(['charge_desc' => 'a']);
                            $slips[$order->enccode] = 'P' . date('y') . '-' . sprintf('%07d', $charge->id);
                        }
                        $code = $slips[$order->enccode];
                    }
                    foreach ($parts as $index => $part) {
                        $stock = $part['stock'];
                        $qty = $part['qty'];
                        $line = $index === 0 ? $order : new DrugOrder();
                        if ($index !== 0) {
                            $line->fill(array_intersect_key($template, array_flip($line->getFillable())));
                            $line->docointkey = 'UDD' . bin2hex(random_bytes(16));
                        }
                        $unitPrice = $stock->chrgcode === $template['orderfrom'] ? (float) $template['pchrgup'] : (float) $stock->fund_unit_price;
                        $line->orderfrom = $stock->chrgcode;
                        $line->pchrgqty = $qty;
                        $line->pchrgup = $unitPrice;
                        $line->pcchrgamt = round($qty * $unitPrice, 2);
                        $line->qtyissued = $qty;
                        $line->estatus = 'S';
                        $line->pcchrgcod = $code;
                        $line->dmdprdte = $stock->dmdprdte;
                        $line->exp_date = $stock->exp_date;
                        $line->item_id = $stock->id;
                        $line->loc_code = $locationId;
                        $line->dodtepost = now();
                        $line->dotmepost = now();
                        $line->save();
                        $stockRemaining[$stock->id] -= $qty;
                        DB::connection('hospital')->table('hospital.dbo.pharm_drug_stocks')->where('id', $stock->id)->update(['stock_bal' => $stockRemaining[$stock->id]]);
                        $stock->retail_price = (float) $stock->fund_unit_price;
                        $this->logStockIssue($stock, $line, $qty, $line->tx_type ?: 'service', $actor, $locationId);
                        if ($line->prescription_data_id) {
                            // Same SQL Server connection keeps the cross-database issuance log in this transaction.
                            $issued = new PrescriptionDataIssued(['presc_data_id' => $line->prescription_data_id, 'docointkey' => $line->docointkey, 'qtyissued' => $qty]);
                            $issued->setConnection('hospital')->save();
                        }
                    }
                    $codes[] = $code;
                }
                return ['ok' => true, 'message' => 'Charge and issuance completed using current stock first, then selected alternate funds.', 'pcchrgcods' => array_values(array_unique($codes))];
            });
        } catch (\Throwable $e) {
            report($e);
            return ['ok' => false, 'message' => 'Alternate-fund processing failed. No changes from this attempt were committed. Refresh the queue and retry; contact support if it persists.', 'pcchrgcods' => []];
        }
    }

    private function cloneEnrollmentForDate($enrollment, $today, $index)
    {
        $docointkey = '0000040' . $enrollment->hpercode . UdddsSchedule::date($today)->format('mdY') . date('His') . $enrollment->orderfrom . $enrollment->dmdcomb . $enrollment->dmdctr . $index;

        $attributes = [
            'docointkey' => $docointkey,
            'enccode' => $enrollment->enccode,
            'hpercode' => $enrollment->hpercode,
            'rxooccid' => '1',
            'rxoref' => '1',
            'dmdcomb' => $enrollment->dmdcomb,
            'repdayno1' => '1',
            'rxostatus' => 'A',
            'rxolock' => 'N',
            'rxoupsw' => 'N',
            'rxoconfd' => 'N',
            'dmdctr' => $enrollment->dmdctr,
            'estatus' => 'U',
            'entryby' => $enrollment->entryby,
            'ordcon' => 'NEWOR',
            'orderupd' => 'ACTIV',
            'locacode' => 'PHARM',
            'orderfrom' => $enrollment->orderfrom,
            'issuetype' => 'c',
            'has_tag' => $enrollment->has_tag,
            'tx_type' => $enrollment->tx_type,
            'ris' => $enrollment->ris ? true : false,
            'pchrgqty' => $enrollment->pchrgqty,
            'pchrgup' => $enrollment->pchrgup,
            'pcchrgamt' => $enrollment->pcchrgamt,
            'dodate' => $today . ' 07:00:00',
            'dotime' => $today . ' 07:00:00',
            'dodtepost' => $today . ' 07:00:00',
            'dotmepost' => $today . ' 07:00:00',
            'dmdprdte' => $enrollment->dmdprdte,
            'exp_date' => $enrollment->exp_date,
            'loc_code' => $enrollment->loc_code,
            'item_id' => $enrollment->item_id,
            'remarks' => $enrollment->remarks,
            'prescription_data_id' => $enrollment->prescription_data_id,
            'prescribed_by' => $enrollment->prescribed_by,
            'deptcode' => $enrollment->deptcode,
            'original_enccode' => $enrollment->original_enccode,
            'order_type' => 'BASIC',
            'uddds_start_date' => $enrollment->uddds_start_date,
            'uddds_end_date' => $enrollment->uddds_end_date,
            'is_uddds' => true,
            'uddds_source_docointkey' => $enrollment->docointkey,
        ];
        if (self::hasIntervalColumn()) {
            $attributes['uddds_interval_days'] = UdddsSchedule::interval($enrollment->uddds_interval_days ?? null);
        }
        DrugOrder::create($attributes);

        return [
            'docointkey' => $docointkey,
            'source' => $enrollment->docointkey,
            'enccode' => $enrollment->enccode,
        ];
    }

    private function issueOne($rxo, $locationId, array $actor)
    {
        $stocks = DB::select(
            "SELECT pharm_drug_stocks.*, hdmhdrprice.dmduprice
                FROM hospital.dbo.pharm_drug_stocks
                JOIN hospital.dbo.hdmhdrprice ON pharm_drug_stocks.dmdprdte = hdmhdrprice.dmdprdte
            WHERE pharm_drug_stocks.dmdcomb = ?
                AND pharm_drug_stocks.dmdctr = ?
                AND pharm_drug_stocks.chrgcode = ?
                AND pharm_drug_stocks.loc_code = ?
                AND pharm_drug_stocks.exp_date > ?
                AND pharm_drug_stocks.stock_bal > 0
            ORDER BY pharm_drug_stocks.exp_date ASC",
            [$rxo->dmdcomb, $rxo->dmdctr, $rxo->orderfrom, $locationId, now()->toDateString()]
        );

        if (!$stocks) {
            return ['ok' => false, 'message' => 'Insufficient Stock Balance. ' . ($rxo->drug_concat ?? '')];
        }

        $totalDeduct = (float) $rxo->pchrgqty;
        $tag = $rxo->tx_type ?: 'service';
        $updated = false;

        foreach ($stocks as $stock) {
            if ($totalDeduct <= 0) {
                break;
            }

            if ($totalDeduct > $stock->stock_bal) {
                $transQty = (float) $stock->stock_bal;
                $totalDeduct -= $stock->stock_bal;
                $stockBal = 0;
            } else {
                $transQty = $totalDeduct;
                $stockBal = $stock->stock_bal - $totalDeduct;
                $totalDeduct = 0;
            }

            DB::update("UPDATE hospital.dbo.pharm_drug_stocks SET stock_bal = ? WHERE id = ?", [$stockBal, $stock->id]);
            $updated = true;

            $this->logStockIssue($stock, $rxo, $transQty, $tag, $actor, $locationId);
        }

        if ($totalDeduct > 0) {
            return ['ok' => false, 'message' => 'Insufficient Stock Balance. ' . ($rxo->drug_concat ?? '')];
        }

        if ($updated) {
            DB::update(
                "UPDATE hospital.dbo.hrxo
                    SET estatus = 'S', qtyissued = ?, dodtepost = ?, dotmepost = ?
                    WHERE docointkey = ? AND (estatus = 'P' OR orderfrom = 'DRUMK' OR pchrgup = 0)",
                [$rxo->pchrgqty, now(), now(), $rxo->docointkey]
            );

            if ($rxo->prescription_data_id) {
                PrescriptionDataIssued::create([
                    'presc_data_id' => $rxo->prescription_data_id,
                    'docointkey' => $rxo->docointkey,
                    'qtyissued' => $rxo->pchrgqty,
                ]);
            }
        }

        return ['ok' => true, 'message' => 'Issued'];
    }

    private function logStockIssue($stock, $rxo, $transQty, $tag, array $actor, $locationId)
    {
        $concat = implode('', explode('_', $stock->drug_concat));

        $issuedDrug = DrugStockIssue::create([
            'stock_id' => $stock->id,
            'docointkey' => $rxo->docointkey,
            'dmdcomb' => $rxo->dmdcomb,
            'dmdctr' => $rxo->dmdctr,
            'loc_code' => $locationId,
            'chrgcode' => $rxo->orderfrom,
            'exp_date' => $stock->exp_date,
            'qty' => $transQty,
            'pchrgup' => $rxo->pchrgup,
            'pcchrgamt' => $rxo->pcchrgamt,
            'status' => 'Issued',
            'user_id' => $actor['user_id'] ?? null,
            'hpercode' => $rxo->hpercode,
            'enccode' => $rxo->enccode,
            'toecode' => $actor['toecode'] ?? 'ADM',
            'pcchrgcod' => $rxo->pcchrgcod,
            'ems' => $tag == 'ems' ? $transQty : false,
            'maip' => $tag == 'maip' ? $transQty : false,
            'wholesale' => $tag == 'wholesale' ? $transQty : false,
            'pay' => $tag == 'pay' ? $transQty : false,
            'opdpay' => $tag == 'opdpay' ? $transQty : false,
            'service' => $tag == 'service' ? $transQty : false,
            'caf' => $tag == 'caf' ? $transQty : false,
            'ris' => $rxo->ris ? true : false,
            'konsulta' => $tag == 'konsulta' ? $transQty : false,
            'pcso' => $tag == 'pcso' ? $transQty : false,
            'phic' => $tag == 'phic' ? $transQty : false,
            'doh_free' => $tag == 'doh_free' ? $transQty : false,
            'dmdprdte' => $stock->dmdprdte,
        ]);

        $log = DrugStockLog::firstOrNew([
            'loc_code' => $locationId,
            'dmdcomb' => $rxo->dmdcomb,
            'dmdctr' => $rxo->dmdctr,
            'chrgcode' => $rxo->orderfrom,
            'unit_cost' => $stock->dmduprice ?? 0,
            'unit_price' => $stock->retail_price,
            'consumption_id' => $actor['consumption_id'] ?? null,
        ]);
        $log->issue_qty += $transQty;
        $log->wholesale += $issuedDrug->wholesale;
        $log->ems += $issuedDrug->ems;
        $log->maip += $issuedDrug->maip;
        $log->caf += $issuedDrug->caf;
        $log->ris += $issuedDrug->ris ? 1 : 0;
        $log->pay += $issuedDrug->pay;
        $log->service += $issuedDrug->service;
        $log->konsulta += $issuedDrug->konsulta;
        $log->pcso += $issuedDrug->pcso;
        $log->phic += $issuedDrug->phic;
        $log->opdpay += $issuedDrug->opdpay;
        $log->doh_free += $issuedDrug->doh_free;
        $log->save();

        $card = DrugStockCard::firstOrNew([
            'chrgcode' => $rxo->orderfrom,
            'loc_code' => $locationId,
            'dmdcomb' => $rxo->dmdcomb,
            'dmdctr' => $rxo->dmdctr,
            'exp_date' => $stock->exp_date,
            'stock_date' => now()->toDateString(),
            'drug_concat' => $concat,
            'dmdprdte' => $stock->dmdprdte,
        ]);
        $card->iss += $transQty;
        $card->bal -= $transQty;
        $card->save();
    }
}
