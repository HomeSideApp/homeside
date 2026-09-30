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
        Schema::create('translations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('translatable_type');
            $table->uuid('translatable_id');
            $table->string('locale', 35);
            $table->string('field');
            $table->text('value');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['translatable_type', 'translatable_id', 'locale', 'field'], 'translations_unique');
            $table->index(['translatable_type', 'translatable_id'], 'translations_entity_index');
            $table->index(['translatable_type', 'translatable_id', 'locale'], 'translations_entity_locale_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
