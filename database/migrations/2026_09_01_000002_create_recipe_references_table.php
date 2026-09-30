<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_references', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('recipe_step_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('referenced_recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->string('path');
            $table->decimal('quantity', 12, 4)->nullable();
            $table->string('unit', 50)->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['recipe_id', 'order']);
            $table->index('referenced_recipe_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_references');
    }
};
