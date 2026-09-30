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
        Schema::create('translation_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('translatable_type');
            $table->uuid('translatable_id');
            $table->string('locale', 35);
            $table->string('status')->default('incomplete');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['translatable_type', 'translatable_id', 'locale'], 'translation_statuses_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translation_statuses');
    }
};
