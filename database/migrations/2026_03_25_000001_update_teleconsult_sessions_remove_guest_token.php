<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'portal';

    public function up(): void
    {
        Schema::connection($this->connection)->table('teleconsult_sessions', function (Blueprint $table) {
            $table->dropColumn('webex_guest_token');
            $table->string('webex_meeting_number')->nullable()->after('webex_sip_address');
            $table->string('webex_meeting_password')->nullable()->after('webex_meeting_number');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('teleconsult_sessions', function (Blueprint $table) {
            $table->string('webex_guest_token', 2000)->nullable()->after('webex_sip_address');
            $table->dropColumn(['webex_meeting_number', 'webex_meeting_password']);
        });
    }
};
