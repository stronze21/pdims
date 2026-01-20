<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop existing unique constraint first
        DB::connection('webapp')->statement('
            IF EXISTS (
                SELECT 1 FROM sys.indexes
                WHERE name = \'prescription_queues_queue_number_unique\'
                AND object_id = OBJECT_ID(\'prescription_queues\')
            )
            DROP INDEX prescription_queues_queue_number_unique ON prescription_queues
        ');

        // Add computed column for date part
        DB::connection('webapp')->statement('
            ALTER TABLE prescription_queues
            ADD queued_date AS CAST(queued_at AS DATE) PERSISTED
        ');

        // Create composite unique index
        DB::connection('webapp')->statement('
            CREATE UNIQUE INDEX uq_queue_number_date
            ON prescription_queues(queue_number, queued_date)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop composite unique index
        DB::connection('webapp')->statement('
            IF EXISTS (
                SELECT 1 FROM sys.indexes
                WHERE name = \'uq_queue_number_date\'
                AND object_id = OBJECT_ID(\'prescription_queues\')
            )
            DROP INDEX uq_queue_number_date ON prescription_queues
        ');

        // Drop computed column
        DB::connection('webapp')->statement('
            IF EXISTS (
                SELECT 1 FROM sys.columns
                WHERE name = \'queued_date\'
                AND object_id = OBJECT_ID(\'prescription_queues\')
            )
            ALTER TABLE prescription_queues DROP COLUMN queued_date
        ');

        // Restore original unique constraint
        DB::connection('webapp')->statement('
            CREATE UNIQUE INDEX prescription_queues_queue_number_unique
            ON prescription_queues(queue_number)
        ');
    }
};
