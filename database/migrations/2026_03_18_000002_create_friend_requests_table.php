<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'portal';

    public function up(): void
    {
        Schema::connection('portal')->create('friend_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_user_id');
            $table->unsignedBigInteger('to_user_id');
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->timestamps();

            $table->foreign('from_user_id')->references('id')->on('app_user_accounts')->onDelete('cascade');
            $table->foreign('to_user_id')->references('id')->on('app_user_accounts')->onDelete('cascade');
            $table->unique(['from_user_id', 'to_user_id']);
            $table->index('to_user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('portal')->dropIfExists('friend_requests');
    }
};
