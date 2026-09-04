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

class UdddsService
{
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

    public function enrollIssuedOrders(array $docointkeys, $startDate, $endDate)
    {
        $start = Carbon::parse($startDate)->toDateString();
        $end = Carbon::parse($endDate)->toDateString();

        if ($end < $start) {
            return ['ok' => false, 'message' => 'End date must be on or after the start date.'];
        }

        foreach ($docointkeys as $docointkey) {
            $order = DrugOrder::where('docointkey', $docointkey)->first();

            if (!$order || !$this->isBasic($order->order_type)) {
                continue;
            }

            DB::update(
                "UPDATE hospital.dbo.hrxo
                    SET is_uddds = 1,
                        uddds_start_date = ?,
                        uddds_end_date = ?,
                        order_type = 'BASIC',
                        uddds_source_docointkey = NULL
                    WHERE docointkey = ?",
                [$start, $end, $docointkey]
            );
        }

        return ['ok' => true, 'message' => 'UDDDS enrollment saved.'];
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
        $today = Carbon::parse($referenceDate ?: now('Asia/Manila'))->toDateString();

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

        $created = [];
        $skipped = 0;

        foreach ($enrollments as $index => $enrollment) {
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
        $today = now('Asia/Manila')->toDateString();
        $params = [$today, $locationId];
        $wardFilter = '';

        if ($wardcode) {
            $wardFilter = ' AND ward.wardcode = ?';
            $params[] = $wardcode;
        }

        return DB::select("
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
                hrxo.pcchrgcod,
                hrxo.loc_code,
                hrxo.uddds_start_date,
                hrxo.uddds_end_date,
                hrxo.uddds_source_docointkey,
                hrxo.order_type,
                hrxo.is_uddds,
                hdmhdr.drug_concat,
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
            INNER JOIN hospital.dbo.hpatroom pat_room ON pat_room.enccode = hrxo.enccode AND pat_room.patrmstat = 'A'
            INNER JOIN hospital.dbo.hward ward ON ward.wardcode = pat_room.wardcode
            LEFT JOIN hospital.dbo.hroom room ON room.rmintkey = pat_room.rmintkey
            LEFT JOIN webapp.dbo.prescription_data pd ON pd.id = hrxo.prescription_data_id
            WHERE hrxo.is_uddds = 1
                AND hrxo.uddds_source_docointkey IS NOT NULL
                AND CAST(hrxo.dodate AS DATE) = ?
                AND (hrxo.estatus = 'U' OR (hrxo.estatus = 'P' AND (hrxo.qtyissued IS NULL OR hrxo.qtyissued = 0)))
                AND (hrxo.loc_code = ? OR hrxo.loc_code IS NULL)
                {$wardFilter}
            ORDER BY ward.wardname, pt.patlast, pt.patfirst, hdmhdr.drug_concat
        ", $params);
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

    public function chargeAndIssue(array $docointkeys, $locationId, array $actor)
    {
        $docointkeys = array_values(array_filter($docointkeys));

        if (empty($docointkeys)) {
            return ['ok' => false, 'message' => 'No UDDDS items selected.', 'pcchrgcods' => []];
        }

        $placeholders = implode(',', array_fill(0, count($docointkeys), '?'));
        $items = DB::select(
            "SELECT hrxo.*, hdmhdr.drug_concat
             FROM hospital.dbo.hrxo
             INNER JOIN hospital.dbo.hdmhdr ON hdmhdr.dmdcomb = hrxo.dmdcomb AND hdmhdr.dmdctr = hrxo.dmdctr
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
            } else {
                $pcchrgcod = $encounterItems[0]->pcchrgcod;
            }

            foreach ($encounterItems as $item) {
                $issued = $this->issueOne($item, $locationId, $actor);
                if (!$issued['ok']) {
                    return ['ok' => false, 'message' => $issued['message'], 'pcchrgcods' => $pcchrgcods];
                }
            }

            if ($pcchrgcod) {
                $pcchrgcods[] = $pcchrgcod;
            }
        }

        return [
            'ok' => true,
            'message' => 'Batch charge and issuance completed.',
            'pcchrgcods' => array_values(array_unique($pcchrgcods)),
        ];
    }

    private function cloneEnrollmentForDate($enrollment, $today, $index)
    {
        $docointkey = '0000040' . $enrollment->hpercode . date('mdYHis') . $enrollment->orderfrom . $enrollment->dmdcomb . $enrollment->dmdctr . $index;

        DrugOrder::create([
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
            'order_by' => $enrollment->order_by,
            'original_enccode' => $enrollment->original_enccode,
            'order_type' => 'BASIC',
            'uddds_start_date' => $enrollment->uddds_start_date,
            'uddds_end_date' => $enrollment->uddds_end_date,
            'is_uddds' => true,
            'uddds_source_docointkey' => $enrollment->docointkey,
        ]);

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
