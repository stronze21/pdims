<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('hospital')->create('pharm_drug_import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source_filename', 255);
            $table->string('source_hash', 64)->index();
            $table->string('source_adapter', 50);
            $table->string('stored_path', 500)->nullable();
            $table->string('status', 30)->default('staged')->index();
            $table->longText('defaults_json')->nullable();
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('ready_count')->default(0);
            $table->unsignedInteger('issue_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('excluded_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->string('uploaded_by', 50)->nullable();
            $table->string('approved_by', 50)->nullable();
            $table->dateTime('committed_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('hospital')->create('pharm_drug_import_rows', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->index();
            $table->string('source_sheet', 100);
            $table->unsignedInteger('source_row');
            $table->longText('raw_json');
            $table->string('generic_name', 255)->nullable()->index();
            $table->string('strength_text', 255)->nullable();
            $table->string('form_text', 255)->nullable();
            $table->string('route_text', 255)->nullable();
            $table->string('size_text', 100)->nullable();
            $table->string('pndf', 1)->nullable();
            $table->string('rxot', 3)->default('RXX');
            $table->string('record_status', 1)->default('A');
            $table->string('atc_code', 35)->nullable();
            $table->longText('technical_spec')->nullable();
            $table->string('gencode', 5)->nullable();
            $table->string('grpcode', 10)->nullable();
            $table->decimal('dmdnost', 12, 2)->nullable();
            $table->string('strecode', 5)->nullable();
            $table->string('formcode', 5)->nullable();
            $table->string('rtecode', 5)->nullable();
            $table->decimal('packvolno', 12, 2)->nullable();
            $table->string('packvolunitcode', 5)->nullable();
            $table->string('row_status', 30)->default('needs_mapping')->index();
            $table->string('issue_code', 50)->nullable()->index();
            $table->longText('issues_json')->nullable();
            $table->string('action', 20)->default('import');
            $table->string('existing_dmdcomb', 12)->nullable();
            $table->unsignedTinyInteger('existing_dmdctr')->nullable();
            $table->string('result_dmdcomb', 12)->nullable();
            $table->unsignedTinyInteger('result_dmdctr')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'source_sheet', 'source_row', 'rtecode'], 'drug_import_row_source_unique');
        });

        Schema::connection('hospital')->create('pharm_drug_import_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('mapping_type', 30);
            $table->string('source_normalized', 255);
            $table->string('target_code', 20);
            $table->string('approved_by', 50)->nullable();
            $table->timestamps();
            $table->unique(['mapping_type', 'source_normalized'], 'drug_import_mapping_unique');
        });

        Schema::connection('hospital')->create('pharm_drug_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('dmdcomb', 12);
            $table->unsignedTinyInteger('dmdctr');
            $table->string('atc_code', 35)->nullable()->index();
            $table->longText('technical_spec')->nullable();
            $table->string('raw_size', 100)->nullable();
            $table->string('classification_level_1', 255)->nullable();
            $table->string('classification_level_2', 255)->nullable();
            $table->string('classification_level_3', 255)->nullable();
            $table->string('classification_level_4', 255)->nullable();
            $table->uuid('source_batch_id')->nullable()->index();
            $table->string('source_sheet', 100)->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->timestamps();
            $table->unique(['dmdcomb', 'dmdctr'], 'drug_metadata_drug_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('hospital')->dropIfExists('pharm_drug_metadata');
        Schema::connection('hospital')->dropIfExists('pharm_drug_import_mappings');
        Schema::connection('hospital')->dropIfExists('pharm_drug_import_rows');
        Schema::connection('hospital')->dropIfExists('pharm_drug_import_batches');
    }
};
