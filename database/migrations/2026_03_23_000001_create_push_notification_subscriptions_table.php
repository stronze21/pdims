<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'portal';

    public function up(): void
    {
        Schema::connection('portal')->create('push_notification_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('ntfy_topic')->unique();
            $table->string('platform', 20)->default('android');
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::connection('portal')->dropIfExists('push_notification_subscriptions');
    }
};
