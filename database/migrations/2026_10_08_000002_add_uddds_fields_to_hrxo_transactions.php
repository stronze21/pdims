<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'order_type' => 'VARCHAR(20) NULL',
            'is_uddds' => 'BIT NULL',
            'uddds_start_date' => 'DATE NULL',
            'uddds_end_date' => 'DATE NULL',
            'uddds_source_docointkey' => 'VARCHAR(50) NULL',
            'uddds_interval_days' => 'INT NULL',
        ];
        foreach (['hrxoissue', 'hrxoreturn'] as $table) {
            foreach ($columns as $column => $definition) {
                DB::connection('hospital')->unprepared("IF COL_LENGTH('dbo.{$table}', '{$column}') IS NULL ALTER TABLE dbo.{$table} ADD {$column} {$definition};");
            }
        }
    }

    public function down(): void
    {
        // Retain historical issue/return snapshots; no destructive rollback.
    }
};
