<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recipe_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index('recipe_id');
        });

        Schema::table('recipe_ingredients', function (Blueprint $table) {
            $table->foreign('section_id')->references('id')->on('recipe_sections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recipe_ingredients', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
        });

        Schema::dropIfExists('recipe_sections');
    }
};
