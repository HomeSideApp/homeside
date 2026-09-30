<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('household_id')->constrained()->cascadeOnDelete();
            $table->string('module');
            $table->boolean('enabled')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['household_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_modules');
    }
};
