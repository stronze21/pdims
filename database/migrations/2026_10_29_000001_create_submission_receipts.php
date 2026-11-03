<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubmissionReceipts extends Migration
{
    public function up()
    {
        if (Schema::connection('hospital')->hasTable('pharm_submission_receipts')) return;
        Schema::connection('hospital')->create('pharm_submission_receipts', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('fingerprint', 64);
            $table->string('state', 16);
            $table->longText('response')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        // Receipts are safety records: retain them across application rollback.
    }
}
