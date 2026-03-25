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
            $table->string('platform', 20)->default('webex')->after('doctor_name');
            $table->string('jitsi_room_name')->nullable()->after('webex_host_key');
            $table->string('jitsi_meeting_link')->nullable()->after('jitsi_room_name');
        });
    }

    public function down(): void
    {
        Schema::table('teleconsult_sessions', function (Blueprint $table) {
            $table->dropColumn(['platform', 'jitsi_room_name', 'jitsi_meeting_link']);
        });
    }
};
