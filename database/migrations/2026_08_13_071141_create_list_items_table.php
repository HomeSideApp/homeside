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
        Schema::create('list_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('list_id')->constrained('shopping_lists')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('custom_name')->nullable();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('unit', 50)->nullable();
            $table->boolean('is_checked')->default(false);
            $table->string('notes')->nullable();
            $table->string('source_type')->nullable();
            $table->uuid('source_id')->nullable();
            $table->decimal('original_quantity', 8, 2)->nullable();
            $table->string('original_unit', 50)->nullable();
            $table->unsignedInteger('scaled_servings')->nullable();
            $table->foreignUuid('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('image_url')->nullable();
            $table->string('icon')->nullable();
            $table->foreignUuid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_items');
    }
};
