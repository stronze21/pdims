<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pharmacy\TempChargedItem;

class CleanupExpiredTempCharges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pharmacy:cleanup-temp-charges';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired temporary charged items (24+ hours old) and restore stock balance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup of expired temporary charged items...');

        try {
            $count = TempChargedItem::cleanupExpired();

            if ($count > 0) {
                $this->info("✓ Successfully cleaned up {$count} expired item(s)");
                $this->info('✓ Stock balances have been restored');
            } else {
                $this->info('✓ No expired items found');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Cleanup failed: ' . $e->getMessage());
            \Log::error('Temp charges cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }
}
