<?php

namespace App\Console\Commands;

use App\Services\Pharmacy\PrescriptionReactivationService;
use Illuminate\Console\Command;

class ReactivatePrescriptionItems extends Command
{
    protected $signature = 'prescription:reactivate-unfinished
        {--dry-run : Preview items without updating them}
        {--ward= : Limit reactivation to current patients in the given ward code}
        {--wardcode= : Alias for --ward}';

    protected $description = 'Automatically reorder unfinished prescription items using the confirmed CDOE quantity rules';

    public function __construct(private readonly PrescriptionReactivationService $reactivationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $wardcode = $this->wardCodeOption();

        if ($wardcode === false) {
            return self::FAILURE;
        }

        $run = $this->reactivationService->reactivateEligible(
            now('Asia/Manila'),
            (bool) $this->option('dry-run'),
            $wardcode
        );

        $this->info('Prescription reorder run at '.$run['run_at']);

        if ($wardcode !== null) {
            $this->line('Ward filter: '.$wardcode);
        }

        if (($run['status'] ?? null) === 'skipped_manual_exists') {
            $this->warn($run['message'] ?? 'Skipped automatic reorder because a manual reorder already ran today.');

            return self::SUCCESS;
        }

        $this->line(($run['dry_run'] ? 'Would reorder' : 'Reordered').': '.$run['count']);

        if ($run['count'] > 0) {
            $rows = collect($run['items'])->take(20)->map(function (array $item) {
                return [
                    'ID' => $item['id'],
                    'Drug' => $item['drug_concat'] ?? $item['dmdcomb'],
                    'Encounter Date' => $item['encounter_date'] ? \Carbon\Carbon::parse($item['encounter_date'])->format('Y-m-d') : null,
                    'Total Qty' => $item['computed_total_qty'],
                    'Issued' => $item['qty_issued'],
                    'Remaining' => $item['computed_remaining_qty'],
                    'Ward' => $item['wardname'] ?? $item['wardcode'] ?? null,
                    'Remark' => $item['remark'],
                    'Duration' => $item['duration_days'],
                ];
            })->all();

            $this->table(['ID', 'Drug', 'Encounter Date', 'Total Qty', 'Issued', 'Remaining', 'Ward', 'Remark', 'Duration'], $rows);

            if ($run['count'] > 20) {
                $this->line('Preview limited to first 20 items.');
            }
        }

        return self::SUCCESS;
    }

    private function wardCodeOption(): string|false|null
    {
        $ward = $this->normaliseOptionValue($this->option('ward'));
        $wardcode = $this->normaliseOptionValue($this->option('wardcode'));

        if ($ward !== null && $wardcode !== null && $ward !== $wardcode) {
            $this->error('Use either --ward or --wardcode, or pass the same ward code to both.');

            return false;
        }

        return $ward ?? $wardcode;
    }

    private function normaliseOptionValue(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
