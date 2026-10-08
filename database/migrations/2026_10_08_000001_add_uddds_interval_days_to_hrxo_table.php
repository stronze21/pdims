<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('hospital')->unprepared("IF COL_LENGTH('dbo.hrxo', 'uddds_interval_days') IS NULL ALTER TABLE dbo.hrxo ADD uddds_interval_days INT NULL;");
    }

    public function down(): void
    {
        // Intentionally retain schedule data; this additive update is not destructive.
    }
};
