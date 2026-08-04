<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('drug-imports:purge-files', function () {
    $batches = \App\Models\Pharmacy\DrugImportBatch::query()
        ->whereNotNull('stored_path')->where('created_at', '<', now()->subDays(90))->get();
    foreach ($batches as $batch) {
        Storage::disk('local')->delete($batch->stored_path);
        $batch->update(['stored_path' => null]);
    }
    $this->info("Purged {$batches->count()} expired drug-import files; audit rows were retained.");
})->purpose('Purge private drug-import workbooks older than 90 days');

Schedule::command('drug-imports:purge-files')->dailyAt('02:30')->withoutOverlapping();
