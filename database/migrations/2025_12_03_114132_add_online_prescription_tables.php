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
        Schema::connection('webapp')->create('prescription_queues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prescription_id');
            $table->string('enccode', 50)->nullable();
            $table->string('hpercode', 50)->nullable();
            $table->string('queue_number', 20)->unique(); // e.g., RX-2025-0001
            $table->string('queue_prefix', 10)->default('RX'); // RX, STAT, etc.
            $table->integer('sequence_number'); // Daily sequence
            $table->string('location_code', 20)->nullable(); // Pharmacy location
            $table->enum('queue_status', ['waiting', 'preparing', 'ready', 'dispensed', 'cancelled'])->default('waiting');
            $table->enum('priority', ['normal', 'urgent', 'stat'])->default('normal');
            $table->timestamp('queued_at')->useCurrent();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('preparing_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('dispensed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('prepared_by', 20)->nullable(); // employeeid
            $table->string('dispensed_by', 20)->nullable(); // employeeid
            $table->string('cancelled_by', 20)->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->integer('estimated_wait_minutes')->nullable();
            $table->string('created_from', 50)->default('EMR'); // EMR, PDIMS, KIOSK
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('prescription_id');
            $table->index('enccode');
            $table->index('hpercode');
            $table->index('queue_number');
            $table->index('queue_status');
            $table->index('queued_at');
            $table->index(['location_code', 'queue_status']);
            $table->index(['location_code', 'queued_at']);
        });

        // Create sequence counter table for daily reset
        Schema::connection('webapp')->create('prescription_queue_sequences', function (Blueprint $table) {
            $table->id();
            $table->date('sequence_date');
            $table->string('location_code', 20);
            $table->string('queue_prefix', 10);
            $table->integer('last_sequence')->default(0);
            $table->timestamps();

            $table->unique(['sequence_date', 'location_code', 'queue_prefix']);
        });

        // Create queue logs table for tracking status changes
        Schema::connection('webapp')->create('prescription_queue_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('queue_id');
            $table->string('status_from', 20)->nullable();
            $table->string('status_to', 20);
            $table->string('changed_by', 20)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('queue_id');
            $table->index('created_at');
        });

        // Create queue display settings table
        Schema::connection('webapp')->create('prescription_queue_display_settings', function (Blueprint $table) {
            $table->id();
            $table->string('location_code', 20);
            $table->integer('display_limit')->default(10); // How many to show on screen
            $table->integer('auto_refresh_seconds')->default(30);
            $table->boolean('show_patient_name')->default(false); // Privacy setting
            $table->boolean('play_sound_alert')->default(true);
            $table->boolean('show_estimated_wait')->default(true);
            $table->string('display_mode', 20)->default('list'); // list, grid, kiosk
            $table->timestamps();

            $table->unique('location_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('webapp')->dropIfExists('prescription_queue_display_settings');
        Schema::connection('webapp')->dropIfExists('prescription_queue_logs');
        Schema::connection('webapp')->dropIfExists('prescription_queue_sequences');
        Schema::connection('webapp')->dropIfExists('prescription_queues');
    }
};
