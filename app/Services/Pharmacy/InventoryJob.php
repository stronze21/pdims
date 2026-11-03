<?php

namespace App\Services\Pharmacy;

use App\Services\SubmissionLedger;

class InventoryJob
{
    public function run($jobClass, $key, callable $operation)
    {
        return app(InventoryMutation::class)->run(function () use ($jobClass, $key, $operation) {
            $response = app(SubmissionLedger::class)->run('inventory-job:' . $jobClass, $key, hash('sha256', $jobClass . ':' . $key), function () use ($operation) {
                $operation();
                return response()->json(['completed' => true]);
            });
            if ($response->getStatusCode() !== 200) throw new \RuntimeException('Inventory job is already in progress or needs review.');
        });
    }
}
