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
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignUuid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('icon')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('needs_image')->default(false)->index();
            $table->boolean('is_approved')->default(false)->index();
            $table->boolean('is_personal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
