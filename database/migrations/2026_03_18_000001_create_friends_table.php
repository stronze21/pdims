<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'portal';

    public function up(): void
    {
        Schema::connection('portal')->create('friends', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('friend_user_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('app_user_accounts')->onDelete('cascade');
            $table->foreign('friend_user_id')->references('id')->on('app_user_accounts')->onDelete('cascade');
            $table->unique(['user_id', 'friend_user_id']);
            $table->index('user_id');
            $table->index('friend_user_id');
        });
    }

    public function down(): void
    {
        Schema::connection('portal')->dropIfExists('friends');
    }
};
