<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prescription_reorder_logs')) {
            return;
        }

        Schema::create('prescription_reorder_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prescription_data_id')->index();
            $table->unsignedBigInteger('prescription_id')->nullable()->index();
            $table->string('source', 20)->index();
            $table->timestamp('reordered_at')->index();
            $table->string('performed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_reorder_logs');
    }
};
