<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\Pharmacy\PrescriptionQueueService;

class CreatePrescriptionQueues extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prescription:create-queues
                            {--date= : Specific date to process (Y-m-d format)}
                            {--location= : Specific location code to process}
                            {--type=* : Encounter types to process (OPD, ER, ADM, ERADM, OPDAD)}
                            {--dry-run : Preview queues without creating them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically create prescription queues from active prescriptions';

    protected $queueService;

    /**
     * Execute the console command.
     */
    public function __construct(PrescriptionQueueService $queueService)
    {
        parent::__construct();
        $this->queueService = $queueService;
    }

    public function handle()
    {
        $this->info('Starting automatic prescription queue creation...');
        $this->newLine();

        // Get options
        $date = $this->option('date') ? $this->option('date') : date('Y-m-d');
        $locationCode = $this->option('location');
        $encounterTypes = $this->option('type') ?: ['OPD', 'ER', 'ADM', 'ERADM', 'OPDAD'];
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No queues will be created');
            $this->newLine();
        }

        // Build and execute query
        $prescriptions = $this->getPrescriptionsForQueue($date, $locationCode, $encounterTypes);

        if ($prescriptions->isEmpty()) {
            $this->info('No prescriptions found to queue.');
            return 0;
        }

        $this->info("Found {$prescriptions->count()} prescriptions to process");
        $this->newLine();

        // Progress bar
        $bar = $this->output->createProgressBar($prescriptions->count());
        $bar->start();

        $stats = [
            'created' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        foreach ($prescriptions as $prescription) {
            $bar->advance();

            try {
                // Check if queue already exists
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
                    // Create queue
                    $result = $this->queueService->createQueue([
                        'prescription_id' => $prescription->prescription_id,
                        'enccode' => $prescription->enccode,
                        'hpercode' => $prescription->hpercode,
                        'location_code' => $prescription->location_code,
                        'priority' => $prescription->priority,
                        'queue_prefix' => $prescription->queue_prefix,
                        'created_by' => $prescription->created_by,
                        'created_from' => $prescription->created_from,
                    ]);

                    if ($result['success']) {
                        $stats['created']++;
                    } else {
                        $stats['errors']++;
                        $this->newLine();
                        $this->error("Error creating queue for prescription {$prescription->prescription_id}: {$result['message']}");
                    }
                } else {
                    $stats['created']++;
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                $this->newLine();
                $this->error("Exception for prescription {$prescription->prescription_id}: {$e->getMessage()}");
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Display summary
        $this->displaySummary($stats, $isDryRun);

        return 0;
    }

    /**
     * Get prescriptions that need to be queued
     */
    private function getPrescriptionsForQueue($date, $locationCode = null, $encounterTypes = [])
    {
        $encounterTypesStr = "'" . implode("','", $encounterTypes) . "'";

        $locationFilter = $locationCode
            ? "AND '{$locationCode}' = '{$locationCode}'"
            : "AND '2' = '2'"; // Default location from query

        // Use BETWEEN for precise date filtering (single day)
        $dateStart = $date . ' 00:00:00';
        $dateEnd = $date . ' 23:59:59';

        $query = "
            SELECT
                presc.id AS prescription_id,
                enc.enccode,
                enc.hpercode,
                CASE
                    WHEN '{$locationCode}' IS NOT NULL THEN '{$locationCode}'
                    ELSE '2'
                END AS location_code,
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM webapp.dbo.prescription_data pd
                        WHERE pd.presc_id = presc.id
                          AND pd.priority = 'U'
                          AND pd.stat = 'A'
                    )
                    THEN 'stat'
                    ELSE 'normal'
                END AS priority,
                enc.toecode AS queue_prefix,
                presc.empid AS created_by,
                opd.tscode AS created_from,
                enc.encdate,
                presc.created_at AS prescription_time
            FROM hospital.dbo.henctr enc
                INNER JOIN hospital.dbo.hopdlog opd ON enc.enccode = opd.enccode
                INNER JOIN webapp.dbo.prescription presc ON enc.enccode = presc.enccode
            WHERE
                enc.encdate BETWEEN '{$dateStart}' AND '{$dateEnd}'
                AND enc.toecode IN ({$encounterTypesStr})
                AND EXISTS (
                    SELECT 1
                    FROM webapp.dbo.prescription_data pd
                    WHERE pd.presc_id = presc.id
                      AND pd.stat = 'A'
                )
                {$locationFilter}
            ORDER BY
                enc.encdate DESC,
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM webapp.dbo.prescription_data pd
                        WHERE pd.presc_id = presc.id
                          AND pd.priority = 'U'
                          AND pd.stat = 'A'
                    )
                    THEN 1
                    ELSE 2
                END,
                presc.created_at DESC
        ";

        return collect(DB::select($query));
    }

    /**
     * Display summary of queue creation
     */
    private function displaySummary($stats, $isDryRun)
    {
        $this->info('=================================');
        $this->info('Queue Creation Summary');
        $this->info('=================================');

        if ($isDryRun) {
            $this->line("<fg=cyan>Would create:</fg=cyan> {$stats['created']} queues");
        } else {
            $this->line("<fg=green>Created:</fg=green> {$stats['created']} queues");
        }

        $this->line("<fg=yellow>Skipped:</fg=yellow> {$stats['skipped']} (already queued)");

        if ($stats['errors'] > 0) {
            $this->line("<fg=red>Errors:</fg=red> {$stats['errors']}");
        }

        $this->info('=================================');
    }
}
