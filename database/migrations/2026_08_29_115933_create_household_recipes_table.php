<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_recipes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('household_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('shared_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_favorite')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['household_id', 'recipe_id']);
            $table->index('household_id');
            $table->index('recipe_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_recipes');
    }
};
