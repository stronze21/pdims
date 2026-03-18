<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'portal';

    public function up(): void
    {
        Schema::connection('portal')->create('patient_conversations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['dm', 'group'])->default('dm');
            $table->string('name')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('app_user_accounts')->onDelete('cascade');
            $table->index('type');
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::connection('portal')->dropIfExists('patient_conversations');
    }
};
