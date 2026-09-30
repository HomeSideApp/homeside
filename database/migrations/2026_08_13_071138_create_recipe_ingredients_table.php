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
        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recipe_id')->constrained()->cascadeOnDelete();
            $table->uuid('section_id')->nullable();
            $table->string('name')->default('');
            $table->foreignUuid('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 8, 2)->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('notes')->nullable();
            $table->string('preparation')->nullable();
            $table->string('quantity_text')->nullable();
            $table->boolean('optional')->default(false);
            $table->text('original_text')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
    }
};
