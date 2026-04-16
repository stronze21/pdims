<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'portal';

    public function up(): void
    {
        if (!Schema::connection('portal')->hasTable('prescription_refill_requests')) {
            return;
        }

        Schema::connection('portal')->table('prescription_refill_requests', function (Blueprint $table) {
            if (!Schema::connection('portal')->hasColumn('prescription_refill_requests', 'request_source')) {
                $table->string('request_source', 20)->default('patient')->after('remarks')->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('portal')->hasTable('prescription_refill_requests')) {
            return;
        }

        Schema::connection('portal')->table('prescription_refill_requests', function (Blueprint $table) {
            if (Schema::connection('portal')->hasColumn('prescription_refill_requests', 'request_source')) {
                $table->dropColumn('request_source');
            }
        });
    }
};
