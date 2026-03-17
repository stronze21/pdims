<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'portal';

    public function up(): void
    {
        Schema::connection('portal')->create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->enum('sender_type', ['patient', 'staff']);
            $table->unsignedBigInteger('sender_id');
            $table->string('sender_name')->nullable();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('chat_conversations')->onDelete('cascade');
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('portal')->dropIfExists('chat_messages');
    }
};
