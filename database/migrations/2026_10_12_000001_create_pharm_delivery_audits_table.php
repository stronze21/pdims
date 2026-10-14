<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePharmDeliveryAuditsTable extends Migration
{
    public function up()
    {
        // Both apps share this hospital table; deploy this migration from one app only.
        if (!Schema::connection('hospital')->hasTable('pharm_delivery_audits')) {
            Schema::connection('hospital')->create('pharm_delivery_audits', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('delivery_id')->index();
                $table->string('action', 20);
                $table->string('user_id');
                $table->string('user_name');
                $table->text('reason')->nullable();
                $table->text('entries');
                $table->timestamp('created_at');
            });
        }
    }

    public function down()
    {
        // Preserve the shared inventory audit trail on rollback.
    }
}
