<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'portal';

    public function up(): void
    {
        if (Schema::connection('portal')->hasTable('prescription_refill_requests')) {
            return;
        }

        Schema::connection('portal')->create('prescription_refill_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->string('hpercode', 50)->nullable();
            $table->string('enccode', 50)->nullable();
            $table->unsignedBigInteger('prescription_id')->nullable()->comment('webapp.dbo.prescription.id');
            $table->unsignedBigInteger('prescription_data_id')->nullable()->comment('webapp.dbo.prescription_data.id');
            $table->string('dmdcomb', 50)->nullable();
            $table->string('dmdctr', 50)->nullable();
            $table->string('drug_name')->nullable();
            $table->decimal('qty_requested', 10, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->enum('status', ['pending', 'approved', 'denied', 'completed'])->default('pending');
            $table->text('admin_remarks')->nullable();
            $table->string('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->index('hpercode');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('portal')->dropIfExists('prescription_refill_requests');
    }
};
