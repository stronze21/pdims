<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('hospital')->create('pharm_temp_charged_items', function (Blueprint $table) {
            $table->id();
            $table->string('docointkey', 100)->index();
            $table->unsignedBigInteger('stock_id')->index();
            $table->string('dmdcomb', 20);
            $table->string('dmdctr', 4);
            $table->string('chrgcode', 20);
            $table->string('loc_code', 20);
            $table->date('exp_date');
            $table->decimal('qty_allocated', 10, 2);
            $table->decimal('unit_price', 10, 2);
            $table->string('pcchrgcod', 50)->index();
            $table->string('enccode', 50);
            $table->string('hpercode', 20);
            $table->dateTime('dmdprdte');
            $table->string('lot_no', 50)->nullable();
            $table->timestamp('charged_at');
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            // Indexes for performance
            $table->index(['docointkey', 'pcchrgcod']);
            $table->index(['stock_id', 'exp_date']);
        });

        // Add comment to table
        DB::connection('hospital')->statement(
            "EXEC sp_addextendedproperty
            @name = N'MS_Description',
            @value = N'Temporary storage for charged items with 24-hour expiry. Used for FEFO allocation before actual issuance.',
            @level0type = N'SCHEMA', @level0name = N'dbo',
            @level1type = N'TABLE', @level1name = N'pharm_temp_charged_items'"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('hospital')->dropIfExists('pharm_temp_charged_items');
    }
};
