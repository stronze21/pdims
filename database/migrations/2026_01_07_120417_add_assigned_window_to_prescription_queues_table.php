<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('webapp')->table('prescription_queues', function (Blueprint $table) {
            $table->tinyInteger('assigned_window')->nullable()->after('location_code')
                ->comment('Dispensing window number (1-8) for concurrent processing');

            $table->index('assigned_window');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('webapp')->table('prescription_queues', function (Blueprint $table) {
            $table->dropIndex(['assigned_window']);
            $table->dropColumn('assigned_window');
        });
    }
};
