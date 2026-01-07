<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\Pharmacy\PrescriptionQueueService;

class CreatePrescriptionQueues extends Command
{
    protected $signature = 'prescription:create-queues
                            {--date= : Specific date to process (Y-m-d format)}
                            {--location= : Specific location code to process}
                            {--type=* : Encounter types to process (OPD, ER, ADM, ERADM, OPDAD)}
                            {--dry-run : Preview queues without creating them}
                            {--debug : Show detailed error messages}';

    protected $description = 'Automatically create prescription queues from active prescriptions';

    protected $queueService;

    public function __construct(PrescriptionQueueService $queueService)
    {
        parent::__construct();
        $this->queueService = $queueService;
    }

    public function handle()
    {
        $this->info('Starting automatic prescription queue creation...');
        $this->newLine();

        $date = $this->option('date') ?: date('Y-m-d');
        $locationCode = $this->option('location');
        $encounterTypes = $this->option('type') ?: ['OPD', 'ER', 'ADM', 'ERADM', 'OPDAD'];
        $isDryRun = $this->option('dry-run');
        $isDebug = $this->option('debug');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No queues will be created');
            $this->newLine();
        }

        $prescriptions = $this->getPrescriptionsForQueue($date, $locationCode, $encounterTypes);

        if ($prescriptions->isEmpty()) {
            $this->info('No prescriptions found to queue.');
            return 0;
        }

        $this->info("Found {$prescriptions->count()} prescriptions to process");
        $this->newLine();

        if ($isDebug && $prescriptions->count() > 0) {
            $this->info('First prescription data:');
            $this->line(json_encode($prescriptions->first(), JSON_PRETTY_PRINT));
            $this->newLine();
        }

        $bar = $this->output->createProgressBar($prescriptions->count());
        $bar->start();

        $stats = ['created' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($prescriptions as $prescription) {
            $bar->advance();

            try {
                $existingQueue = DB::connection('webapp')
                    ->table('prescription_queues')
                    ->where('prescription_id', $prescription->prescription_id)
                    ->whereIn('queue_status', ['waiting', 'preparing', 'ready'])
                    ->first();

                if ($existingQueue) {
                    $stats['skipped']++;
                    continue;
                }

                if (!$isDryRun) {
                    $queueData = [
                        'prescription_id' => $prescription->prescription_id,
                        'enccode' => $prescription->enccode,
                        'hpercode' => $prescription->hpercode,
                        'location_code' => $prescription->location_code ?? '2',
                        'priority' => $prescription->priority ?? 'normal',
                        'queue_prefix' => $prescription->queue_prefix ?? 'RX',
                        'created_by' => $prescription->created_by ?? 'system',
                        'created_from' => 'artisan-auto',
                    ];

                    if ($isDebug) {
                        $this->newLine();
                        $this->line("Creating queue:");
                        $this->line(json_encode($queueData, JSON_PRETTY_PRINT));
                    }

                    $result = $this->queueService->createQueue($queueData);

                    if ($result['success']) {
                        $stats['created']++;
                        if ($isDebug) {
                            $this->info("✓ Created: {$result['queue']->queue_number}");
                        }
                    } else {
                        $stats['errors']++;
                        $this->newLine();
                        $this->error("✗ Prescription {$prescription->prescription_id}: {$result['message']}");
                        if (isset($result['error']) && $isDebug) {
                            $this->line("   {$result['error']}");
                        }
                    }
                } else {
                    $stats['created']++;
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                $this->newLine();
                $this->error("✗ Exception: Prescription {$prescription->prescription_id}");
                $this->line("   {$e->getMessage()}");
                if ($isDebug) {
                    $this->line("   {$e->getFile()}:{$e->getLine()}");
                }
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->displaySummary($stats, $isDryRun);

        return $stats['errors'] > 0 ? 1 : 0;
    }

    private function getPrescriptionsForQueue($date, $locationCode = null, $encounterTypes = [])
    {
        $encounterTypesStr = "'" . implode("','", $encounterTypes) . "'";
        $defaultLocation = $locationCode ?? '2';
        $dateStart = $date . ' 00:00:00';
        $dateEnd = $date . ' 23:59:59';

        $query = "
            SELECT
                presc.id AS prescription_id,
                enc.enccode,
                enc.hpercode,
                '{$defaultLocation}' AS location_code,
                enc.toecode,
                CASE
                    WHEN EXISTS (
                        SELECT 1 FROM webapp.dbo.prescription_data pd
                        WHERE pd.presc_id = presc.id
                          AND pd.order_type = 'STAT' AND pd.stat = 'A'
                    ) THEN 'stat'
                    WHEN EXISTS (
                        SELECT 1 FROM webapp.dbo.prescription_data pd
                        WHERE pd.presc_id = presc.id
                          AND pd.order_type IN ('OR', 'G24') AND pd.stat = 'A'
                    ) THEN 'urgent'
                    ELSE 'normal'
                END AS priority,
                CASE
                    WHEN EXISTS (
                        SELECT 1 FROM webapp.dbo.prescription_data pd
                        WHERE pd.presc_id = presc.id
                          AND pd.order_type = 'STAT' AND pd.stat = 'A'
                    ) THEN 'STAT'
                    ELSE enc.toecode
                END AS queue_prefix,
                presc.empid AS created_by,
                enc.encdate,
                presc.created_at AS prescription_time
            FROM hospital.dbo.henctr enc WITH (NOLOCK)
            INNER JOIN hospital.dbo.hopdlog opd WITH (NOLOCK) ON enc.enccode = opd.enccode
            INNER JOIN webapp.dbo.prescription presc WITH (NOLOCK) ON enc.enccode = presc.enccode
            WHERE enc.encdate BETWEEN '{$dateStart}' AND '{$dateEnd}'
              AND enc.toecode IN ({$encounterTypesStr})
              AND enc.encstat = 'A'
              AND EXISTS (
                  SELECT 1 FROM webapp.dbo.prescription_data pd WITH (NOLOCK)
                  WHERE pd.presc_id = presc.id AND pd.stat = 'A'
              )
              AND NOT EXISTS (
                  SELECT 1 FROM webapp.dbo.prescription_queues pq WITH (NOLOCK)
                  WHERE pq.prescription_id = presc.id
                    AND pq.queue_status IN ('waiting', 'preparing', 'ready')
              )
            ORDER BY
                CASE
                    WHEN EXISTS (
                        SELECT 1 FROM webapp.dbo.prescription_data pd
                        WHERE pd.presc_id = presc.id
                          AND pd.order_type = 'STAT' AND pd.stat = 'A'
                    ) THEN 1
                    WHEN EXISTS (
                        SELECT 1 FROM webapp.dbo.prescription_data pd
                        WHERE pd.presc_id = presc.id
                          AND pd.order_type IN ('OR', 'G24') AND pd.stat = 'A'
                    ) THEN 2
                    ELSE 3
                END,
                enc.encdate ASC,
                presc.created_at ASC
        ";

        return collect(DB::select($query));
    }

    private function displaySummary($stats, $isDryRun)
    {
        $this->info('=================================');
        $this->info('Queue Creation Summary');
        $this->info('=================================');

        if ($isDryRun) {
            $this->line("<fg=cyan>Would create:</> {$stats['created']} queues");
        } else {
            $this->line("<fg=green>Created:</> {$stats['created']} queues");
        }

        $this->line("<fg=yellow>Skipped:</> {$stats['skipped']} (already queued)");

        if ($stats['errors'] > 0) {
            $this->line("<fg=red>Errors:</> {$stats['errors']}");
            $this->newLine();
            $this->warn('Run with --debug flag for detailed error information');
        }

        $this->info('=================================');
    }
}
