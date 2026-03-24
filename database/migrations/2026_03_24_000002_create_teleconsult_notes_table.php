<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'portal';

    public function up(): void
    {
        Schema::connection($this->connection)->create('teleconsult_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teleconsult_session_id');
            $table->string('doctor_employee_id');
            $table->text('subjective')->nullable();
            $table->text('objective')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->text('additional_notes')->nullable();
            $table->timestamps();

            $table->foreign('teleconsult_session_id')
                ->references('id')
                ->on('teleconsult_sessions')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('teleconsult_notes');
    }
};
