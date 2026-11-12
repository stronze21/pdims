<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('webapp')->table('prescription_queue_display_settings', function (Blueprint $table) {
            $table->boolean('show_waiting_column')->default(false);
        });
    }

    public function down(): void
    {
        Schema::connection('webapp')->table('prescription_queue_display_settings', function (Blueprint $table) {
            $table->dropColumn('show_waiting_column');
        });
    }
};
