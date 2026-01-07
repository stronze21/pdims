<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Pharmacy\PrescriptionQueueService;

class QueueMaintenanceTasks extends Command
{
    protected $signature = 'queue:maintenance {task?}
                            {--cleanup-days=30 : Days to keep old queues}';

    protected $description = 'Run maintenance tasks for prescription queue system';

    protected $queueService;

    public function __construct(PrescriptionQueueService $queueService)
    {
        parent::__construct();
        $this->queueService = $queueService;
    }

    public function handle()
    {
        $task = $this->argument('task');

        if (!$task) {
            $this->runAllTasks();
            return 0;
        }

        match ($task) {
            'reset-sequences' => $this->resetSequences(),
            'cleanup-old' => $this->cleanupOld(),
            'generate-stats' => $this->generateStats(),
            default => $this->error("Unknown task: {$task}")
        };

        return 0;
    }

    private function runAllTasks()
    {
        $this->info('Running all maintenance tasks...');
        $this->newLine();

        $this->resetSequences();
        $this->cleanupOld();
        $this->generateStats();

        $this->newLine();
        $this->info('All maintenance tasks completed!');
    }

    private function resetSequences()
    {
        $this->info('Resetting daily sequences...');

        $result = $this->queueService->resetDailySequences();

        if ($result['success']) {
            $this->line("<fg=green>✓</> {$result['message']}");
        } else {
            $this->error("✗ Failed: {$result['message']}");
        }
    }

    private function cleanupOld()
    {
        $days = $this->option('cleanup-days');
        $this->info("Cleaning up queues older than {$days} days...");

        $result = $this->queueService->cleanupOldQueues($days);

        if ($result['success']) {
            $this->line("<fg=green>✓</> {$result['message']}");
        } else {
            $this->error("✗ Failed: {$result['message']}");
        }
    }

    private function generateStats()
    {
        $this->info('Generating today\'s statistics...');

        try {
            $locations = \DB::connection('hospital')
                ->table('pharm_locations')
                ->where('deleted_at', null)
                ->get();

            $totalQueues = 0;
            $totalDispensed = 0;
            $avgWaitTime = 0;

            foreach ($locations as $location) {
                $stats = $this->queueService->getLocationStats($location->id, today());
                $totalQueues += $stats['total'];
                $totalDispensed += $stats['dispensed'];
                $avgWaitTime += $stats['avg_wait_time'];
            }

            $avgWaitTime = $locations->count() > 0
                ? round($avgWaitTime / $locations->count())
                : 0;

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Queues', $totalQueues],
                    ['Dispensed', $totalDispensed],
                    ['Avg Wait Time', "{$avgWaitTime} min"],
                    ['Locations', $locations->count()],
                ]
            );

            $this->line("<fg=green>✓</> Statistics generated successfully");
        } catch (\Exception $e) {
            $this->error("✗ Failed: {$e->getMessage()}");
        }
    }
}
