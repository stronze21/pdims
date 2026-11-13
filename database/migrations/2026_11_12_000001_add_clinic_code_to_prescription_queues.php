<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('webapp')->table('prescription_queues', function (Blueprint $table) {
            $table->string('clinic_code', 20)->nullable();
            $table->index(['location_code', 'clinic_code', 'queue_status', 'queued_at'], 'prescription_queues_clinic_lookup');
        });
    }

    public function down(): void
    {
        Schema::connection('webapp')->table('prescription_queues', function (Blueprint $table) {
            $table->dropIndex('prescription_queues_clinic_lookup');
            $table->dropColumn('clinic_code');
        });
    }
};
