<?php

namespace App\Console\Commands;

use App\Services\Pharmacy\UdddsService;
use Illuminate\Console\Command;

class GenerateUdddsDailyOrders extends Command
{
    protected $signature = 'uddds:generate-daily
        {--dry-run : Preview clones without inserting them}
        {--date= : Generate for a specific date (Y-m-d)}';

    protected $description = 'Create pending UDDDS unit-dose orders for enrolled Basic (standing) meds';

    public function __construct(private readonly UdddsService $udddsService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $run = $this->udddsService->generateDaily($this->option('date') ?: null, (bool) $this->option('dry-run'));

        $this->info('UDDDS generate run at '.$run['run_at']);
        $this->line('Date: '.$run['date']);
        $this->line(($run['dry_run'] ? 'Would create' : 'Created').': '.$run['count']);
        $this->line('Skipped: '.$run['skipped']);

        return self::SUCCESS;
    }
}
