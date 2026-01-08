<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('webapp')->table('prescription_queues', function (Blueprint $table) {
            // Add new charging-related columns
            $table->datetime('charging_at')->nullable()->after('preparing_at');
            $table->datetime('cashier_called_at')->nullable()->after('charging_at');
            $table->string('charged_by', 20)->nullable()->after('prepared_by');
            $table->string('charge_slip_no', 50)->nullable()->after('charged_by');

            // Add skip_count if it doesn't exist
            if (!Schema::connection('webapp')->hasColumn('prescription_queues', 'skip_count')) {
                $table->integer('skip_count')->default(0)->after('assigned_window');
            }
        });

        // Update the queue_status enum to include 'charging'
        // Old: waiting, preparing, ready, dispensed, cancelled
        // New: waiting, preparing, charging, ready, dispensed, cancelled
        DB::connection('webapp')->statement("
            ALTER TABLE prescription_queues
            DROP CONSTRAINT IF EXISTS prescription_queues_queue_status_check
        ");

        DB::connection('webapp')->statement("
            ALTER TABLE prescription_queues
            ADD CONSTRAINT prescription_queues_queue_status_check
            CHECK (queue_status IN ('waiting', 'preparing', 'charging', 'ready', 'dispensed', 'cancelled'))
        ");
    }

    public function down(): void
    {
        Schema::connection('webapp')->table('prescription_queues', function (Blueprint $table) {
            $table->dropColumn(['charging_at', 'charged_by', 'charge_slip_no']);
        });

        // Revert to old enum values
        DB::connection('webapp')->statement("
            ALTER TABLE prescription_queues
            DROP CONSTRAINT IF EXISTS prescription_queues_queue_status_check
        ");

        DB::connection('webapp')->statement("
            ALTER TABLE prescription_queues
            ADD CONSTRAINT prescription_queues_queue_status_check
            CHECK (queue_status IN ('waiting', 'preparing', 'ready', 'dispensed', 'cancelled'))
        ");
    }
};
