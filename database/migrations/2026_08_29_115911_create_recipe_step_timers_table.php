<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_step_timers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recipe_step_id')->constrained('recipe_steps')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index('recipe_step_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_step_timers');
    }
};
