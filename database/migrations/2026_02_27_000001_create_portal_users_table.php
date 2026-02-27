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
        Schema::connection('portal')->create('portal_users', function (Blueprint $table) {
            $table->id();
            $table->string('hpercode', 15)->nullable();
            $table->string('hospital_no', 50)->nullable();
            $table->string('patlast', 50);
            $table->string('patfirst', 50);
            $table->string('patmiddle', 50)->nullable();
            $table->string('patsuffix', 10)->nullable();
            $table->string('email', 100)->unique();
            $table->string('contact_no', 20)->nullable();
            $table->string('password', 255);
            $table->date('patbdate')->nullable();
            $table->char('patsex', 1)->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->string('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('hpercode');
            $table->index('hospital_no');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('portal')->dropIfExists('portal_users');
    }
};
