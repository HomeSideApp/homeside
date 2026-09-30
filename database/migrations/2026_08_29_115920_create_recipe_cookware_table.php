<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_cookware', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('section_id')->nullable()->constrained('recipe_sections')->nullOnDelete();
            $table->string('name');
            $table->string('type')->default('tool');
            $table->unsignedInteger('quantity')->nullable();
            $table->string('quantity_text')->nullable();
            $table->string('unit')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index('recipe_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_cookware');
    }
};
