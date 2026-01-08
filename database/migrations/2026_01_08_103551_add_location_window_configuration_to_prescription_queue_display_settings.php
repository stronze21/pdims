<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('webapp')->table('prescription_queue_display_settings', function (Blueprint $table) {
            // Add window/counter configuration
            $table->integer('pharmacy_windows')->default(3)->after('display_limit');
            $table->integer('dispensing_counters')->default(7)->after('pharmacy_windows');
            $table->boolean('require_cashier')->default(true)->after('dispensing_counters');
            $table->string('cashier_location', 100)->nullable()->after('require_cashier');
        });
    }

    public function down(): void
    {
        Schema::connection('webapp')->table('prescription_queue_display_settings', function (Blueprint $table) {
            $table->dropColumn(['pharmacy_windows', 'dispensing_counters', 'require_cashier', 'cashier_location']);
        });
    }
};
