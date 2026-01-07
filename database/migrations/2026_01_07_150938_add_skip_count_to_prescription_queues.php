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
            $table->integer('skip_count')->default(0)->after('assigned_window')
                ->comment('Number of times this queue was skipped');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('webapp')->table('prescription_queues', function (Blueprint $table) {
            $table->dropColumn('skip_count');
        });
    }
};
