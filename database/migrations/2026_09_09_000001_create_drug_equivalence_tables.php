<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('hospital')->create('pharm_drug_equivalence_groups', function (Blueprint $table) {
            $table->id();
            $table->string('canonical_dmdcomb', 12);
            $table->unsignedTinyInteger('canonical_dmdctr');
            $table->string('status', 1)->default('A')->index();
            $table->string('created_by', 50)->nullable();
            $table->timestamps();
        });

        Schema::connection('hospital')->create('pharm_drug_equivalence_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->index();
            $table->string('dmdcomb', 12);
            $table->unsignedTinyInteger('dmdctr');
            $table->string('added_by', 50)->nullable();
            $table->timestamps();

            $table->unique(['dmdcomb', 'dmdctr'], 'drug_equivalence_member_unique');
            $table->unique(['group_id', 'dmdcomb', 'dmdctr'], 'drug_equivalence_group_member_unique');
        });

        Schema::connection('hospital')->create('pharm_drug_equivalence_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->index();
            $table->string('action', 40)->index();
            $table->string('dmdcomb', 12)->nullable();
            $table->unsignedTinyInteger('dmdctr')->nullable();
            $table->string('actor', 50)->nullable();
            $table->longText('details_json')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('hospital')->dropIfExists('pharm_drug_equivalence_audits');
        Schema::connection('hospital')->dropIfExists('pharm_drug_equivalence_members');
        Schema::connection('hospital')->dropIfExists('pharm_drug_equivalence_groups');
    }
};
