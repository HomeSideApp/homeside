<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('parent_id')->nullable()->constrained('recipe_collections')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('path');
            $table->timestamps();

            $table->index(['owner_id', 'parent_id']);
            $table->unique(['owner_id', 'parent_id', 'slug']);
            $table->unique(['owner_id', 'path']);
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->foreign('collection_id')->references('id')->on('recipe_collections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropForeign(['collection_id']);
        });

        Schema::dropIfExists('recipe_collections');
    }
};
