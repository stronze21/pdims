<?php

namespace App\Services\Pharmacy\Reports;

final class ReportDefinition
{
    public const TITLES = [
        'issuance' => 'Medicine issuance',
        'returns' => 'Medicine returns',
        'reconciliation' => 'Stock reconciliation',
        'deliveries' => 'Delivery receipts',
        'expiry' => 'Stock expiry',
    ];

    public static function columns(string $report, bool $grouped = false): array
    {
        $identity = ['medicine' => 'Medicine', 'location' => 'Location', 'fund' => 'Fund'];

        if ($grouped) {
            $identity['medicine'] = 'Equivalent group / medicine';
            $identity['member_count'] = 'Medicines represented';

            return match ($report) {
                'issuance', 'returns' => $identity + ['quantity' => 'Quantity'],
                'deliveries' => $identity + ['status' => 'Status', 'quantity' => 'Listed quantity',
                    'received_quantity' => 'Received quantity', 'received_cost' => 'Received cost'],
                'expiry' => $identity + ['expiry_date' => 'Expiry', 'status' => 'Status', 'quantity' => 'Remaining quantity'],
                'reconciliation' => $identity + array_diff_key(self::columns($report), $identity + ['price_key' => '']),
                default => [],
            };
        }

        return match ($report) {
            'issuance', 'returns' => ['event_date' => 'Transaction date'] + $identity + [
                'patient' => 'Patient', 'patient_no' => 'Patient number', 'reference' => 'Charge slip',
                'order_reference' => 'Order reference', 'quantity' => 'Quantity', 'staff' => 'Staff',
            ],
            'deliveries' => ['event_date' => 'Delivery date'] + $identity + [
                'reference' => 'Invoice / PO', 'lot' => 'Lot', 'expiry_date' => 'Expiry',
                'status' => 'Status', 'quantity' => 'Listed quantity', 'unit_cost' => 'Unit cost',
                'received_quantity' => 'Received quantity', 'received_cost' => 'Received cost',
            ],
            'expiry' => $identity + ['lot' => 'Lot', 'expiry_date' => 'Expiry', 'status' => 'Status', 'quantity' => 'Remaining quantity'],
            'reconciliation' => $identity + [
                'price_key' => 'Batch / price key', 'opening' => 'Recorded opening',
                'receipts' => 'Net purchase receipts', 'transfer_in' => 'Net transfers in',
                'transfer_out' => 'Net transfers out', 'patient_issues' => 'Patient issues',
                'patient_returns' => 'Patient returns', 'ward_issues' => 'Ward issues',
                'ward_returns' => 'Ward returns*', 'pullouts' => 'Pullouts', 'adjustments' => 'Adjustments',
                'expected' => 'Calculated closing', 'closing' => 'Recorded closing',
                'difference' => 'Unexplained difference', 'status' => 'Reconciliation status',
            ],
            default => [],
        };
    }

    public static function numeric(string $column): bool
    {
        return in_array($column, ['quantity', 'unit_cost', 'received_quantity', 'received_cost', 'member_count',
            'opening', 'receipts', 'transfer_in', 'transfer_out', 'patient_issues', 'patient_returns',
            'ward_issues', 'ward_returns', 'pullouts', 'adjustments', 'expected', 'closing', 'difference'], true);
    }

    public static function totals(string $report): array
    {
        return match ($report) {
            'deliveries' => ['quantity', 'received_quantity', 'received_cost'],
            'reconciliation' => ['receipts', 'transfer_in', 'transfer_out', 'patient_issues', 'patient_returns',
                'ward_issues', 'ward_returns', 'pullouts', 'adjustments'],
            default => ['quantity'],
        };
    }

    public static function notes(string $report, bool $grouped = false): array
    {
        $notes = match ($report) {
            'reconciliation' => [
                'Quantities are grouped by medicine, location, fund and batch/price key before display. No inventory is changed.',
                'Opening uses the preceding day’s stock cards only when an opening reference is present. Historical closing uses end-date cards with an opening reference; today’s closing uses current stock. Missing snapshots remain blank.',
                'Transfers and ward issues use daily stock-card references. Purchase receipts use delivery lock/reversal audit events; legacy receipts without an audit use the delivery’s latest recorded update. Patient issues and returns use transaction events. RIS-tagged patient issues are excluded from reconciliation because ward stock was already issued.',
                '* Ward returns use the ward record’s latest update because legacy records do not preserve a separate return timestamp. Pushed emergency purchases use their latest update as the stock receipt date. Daily transfers can be netted by later reversals. These figures require review and are not certified historical balances.',
                'Pullouts and adjustments use their own records. Historical stock identity can change after edits. Unclassified receipts, missing records and mismatched batch keys appear as unexplained differences; a zero difference alone does not verify history.',
            ],
            'expiry' => ['Current positive stock only, as of generation time. Date-range filters do not apply. Near expiry includes today through the selected horizon; expired means before today. Undated stock is available under “Unknown expiry”.'],
            'deliveries' => ['Received totals include only currently locked deliveries with delivered items. Reverted and pending deliveries remain visible but contribute zero to received totals. This is current delivery status, not a historical inventory ledger.'],
            default => ['Transactions are selected by their actual issue or return date, including returns for orders issued in an earlier period.'],
        };

        if ($grouped) {
            $notes[] = 'Equivalent grouping uses current active drug-equivalence groups and their canonical medicine labels. Ungrouped medicines and members of inactive groups remain separate. Quantities are summarized within each fund code and location; funds are never combined. Medicines represented counts only medicines with matching report rows.';
            $notes[] = match ($report) {
                'reconciliation' => 'Batch/price rows are combined only after reconciliation inputs are calculated. If any included row lacks an opening or closing snapshot, that grouped balance remains unavailable.',
                'expiry' => 'Expiry dates and statuses remain separate in grouped results. Lot details are available with grouping turned off.',
                'deliveries' => 'Delivery statuses remain separate. Received costs are summed from the original items; different unit costs are not averaged. Invoice and lot details are available with grouping turned off.',
                default => 'Grouped results summarize the filtered period. Patient, transaction-date, staff and charge-slip details are available with grouping turned off.',
            };
        }

        return $notes;
    }
}
