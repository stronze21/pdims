<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('hospital')->table('hrxo', function (Blueprint $table) {
            $table->string('order_type', 10)->nullable();
            $table->date('uddds_start_date')->nullable();
            $table->date('uddds_end_date')->nullable();
            $table->boolean('is_uddds')->default(false);
            $table->string('uddds_source_docointkey', 100)->nullable();
            $table->index('is_uddds', 'idx_hrxo_is_uddds');
            $table->index('uddds_source_docointkey', 'idx_hrxo_uddds_source');
        });
    }

    public function down(): void
    {
        Schema::connection('hospital')->table('hrxo', function (Blueprint $table) {
            $table->dropIndex('idx_hrxo_is_uddds');
            $table->dropIndex('idx_hrxo_uddds_source');
            $table->dropColumn([
                'order_type',
                'uddds_start_date',
                'uddds_end_date',
                'is_uddds',
                'uddds_source_docointkey',
            ]);
        });
    }
};
