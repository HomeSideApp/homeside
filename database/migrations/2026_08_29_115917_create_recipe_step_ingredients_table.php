<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_step_ingredients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recipe_step_id')->constrained('recipe_steps')->cascadeOnDelete();
            $table->foreignUuid('recipe_ingredient_id')->constrained('recipe_ingredients')->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['recipe_step_id', 'recipe_ingredient_id'], 'rsi_step_ingr_unique');
            $table->index('recipe_step_id');
            $table->index('recipe_ingredient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_step_ingredients');
    }
};
