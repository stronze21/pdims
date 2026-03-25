<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'portal';

    public function up(): void
    {
        Schema::connection($this->connection)->create('teleconsult_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('patient_id');
            $table->string('doctor_employee_id');
            $table->string('doctor_name')->nullable();
            $table->string('webex_meeting_id')->nullable();
            $table->string('webex_meeting_link', 500)->nullable();
            $table->string('webex_sip_address')->nullable();
            $table->string('webex_guest_token', 2000)->nullable();
            $table->enum('status', [
                'scheduled',
                'waiting',
                'in_progress',
                'completed',
                'cancelled',
                'no_show',
            ])->default('scheduled');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->timestamps();

            $table->index('appointment_id');
            $table->index('patient_id');
            $table->index('status');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('teleconsult_sessions');
    }
};
