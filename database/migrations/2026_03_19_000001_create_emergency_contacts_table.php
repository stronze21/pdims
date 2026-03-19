<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'portal';

    public function up(): void
    {
        Schema::connection('portal')->create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category'); // e.g. "Hospital", "Ambulance", "Police", "Fire", "Hotline"
            $table->string('phone');
            $table->string('label')->nullable(); // e.g. "Emergency Room", "Trunkline", "Admitting Section"
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('category');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::connection('portal')->dropIfExists('emergency_contacts');
    }
};
