<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('hospital')->create('pharm_clinical_ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180)->unique();
            $table->string('created_by', 50)->nullable();
            $table->timestamps();
        });
        Schema::connection('hospital')->create('pharm_clinical_records', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30)->index();
            $table->string('record_key', 160)->index();
            $table->unsignedInteger('revision');
            $table->string('status', 20)->default('draft')->index();
            $table->longText('payload');
            $table->text('source');
            $table->date('reviewed_on');
            $table->string('author', 50);
            $table->string('approved_by', 50)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['record_key', 'revision'], 'clinical_record_revision_unique');
        });
        Schema::connection('hospital')->create('pharm_clinical_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('record_id')->index();
            $table->string('action', 30);
            $table->string('actor', 50);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('hospital')->dropIfExists('pharm_clinical_audits');
        Schema::connection('hospital')->dropIfExists('pharm_clinical_records');
        Schema::connection('hospital')->dropIfExists('pharm_clinical_ingredients');
    }
};
