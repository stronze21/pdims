<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'portal';

    public function up(): void
    {
        Schema::connection('portal')->create('fitness_goals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->string('title');
            $table->string('habit_type', 50);
            $table->string('unit', 50);
            $table->decimal('target_value', 10, 2);
            $table->string('frequency', 50)->default('daily');
            $table->string('goal_category', 50)->default('daily_habit');
            $table->string('source_type', 50)->default('self_managed');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_id');
            $table->index(['patient_id', 'is_active']);
            $table->index(['patient_id', 'habit_type']);
        });

        Schema::connection('portal')->create('fitness_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('goal_id')->nullable();
            $table->string('title');
            $table->string('habit_type', 50);
            $table->decimal('value', 10, 2);
            $table->string('unit', 50);
            $table->dateTime('logged_at');
            $table->string('source_type', 50)->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('patient_id');
            $table->index(['patient_id', 'logged_at']);
            $table->index(['patient_id', 'habit_type']);
            $table->index('goal_id');
        });

        Schema::connection('portal')->create('fitness_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('goal_id')->nullable();
            $table->string('title');
            $table->string('habit_type', 50);
            $table->string('time_of_day', 5);
            $table->json('days_of_week');
            $table->text('message')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->string('source_type', 50)->default('self_managed');
            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_id');
            $table->index(['patient_id', 'is_enabled']);
            $table->index('goal_id');
        });
    }

    public function down(): void
    {
        Schema::connection('portal')->dropIfExists('fitness_reminders');
        Schema::connection('portal')->dropIfExists('fitness_logs');
        Schema::connection('portal')->dropIfExists('fitness_goals');
    }
};
