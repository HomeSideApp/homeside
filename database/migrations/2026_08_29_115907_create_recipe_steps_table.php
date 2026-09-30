<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('section_id')->nullable()->constrained('recipe_sections')->nullOnDelete();
            $table->text('description');
            $table->string('image_path')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index('recipe_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_steps');
    }
};
