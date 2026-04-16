<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prescription_reorder_run_logs')) {
            return;
        }

        Schema::create('prescription_reorder_run_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20)->index();
            $table->string('status', 20)->index();
            $table->boolean('dry_run')->default(false);
            $table->unsignedInteger('reordered_count')->default(0);
            $table->timestamp('run_at')->index();
            $table->string('performed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_reorder_run_logs');
    }
};
