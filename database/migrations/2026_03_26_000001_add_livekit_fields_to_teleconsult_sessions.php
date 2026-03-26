<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'portal';

    public function up(): void
    {
        Schema::table('teleconsult_sessions', function (Blueprint $table) {
            $table->string('livekit_room_name')->nullable()->after('jitsi_meeting_link');
            $table->text('livekit_token')->nullable()->after('livekit_room_name');
        });
    }

    public function down(): void
    {
        Schema::table('teleconsult_sessions', function (Blueprint $table) {
            $table->dropColumn(['livekit_room_name', 'livekit_token']);
        });
    }
};
