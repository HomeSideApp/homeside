<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('derived_from_recipe_id')->nullable()->constrained('recipes')->nullOnDelete();
            $table->uuid('collection_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('servings')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('yield_text')->nullable();
            $table->unsignedInteger('prep_time_seconds')->nullable();
            $table->unsignedInteger('cook_time_seconds')->nullable();
            $table->unsignedInteger('total_time_seconds')->nullable();
            $table->string('difficulty')->nullable();
            $table->string('cuisine')->nullable();
            $table->string('locale')->nullable();
            $table->string('cooking_method')->nullable();
            $table->string('recipe_category')->nullable();
            $table->json('suitable_for_diet')->nullable();
            $table->json('keywords')->nullable();
            $table->string('author')->nullable();
            $table->string('source_url')->nullable();
            $table->string('source_name')->nullable();
            $table->string('source_type')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index('owner_id');
            $table->index('derived_from_recipe_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
