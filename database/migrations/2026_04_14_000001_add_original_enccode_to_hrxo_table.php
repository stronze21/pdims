<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrxo', function (Blueprint $table) {
            $table->string('original_enccode', 50)->nullable()->after('order_by');
            $table->index('original_enccode', 'idx_hrxo_original_enccode');
        });
    }

    public function down(): void
    {
        Schema::table('hrxo', function (Blueprint $table) {
            $table->dropIndex('idx_hrxo_original_enccode');
            $table->dropColumn('original_enccode');
        });
    }
};
