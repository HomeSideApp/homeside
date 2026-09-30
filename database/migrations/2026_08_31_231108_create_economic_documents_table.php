<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economic_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('household_id')->nullable()->references('id')->on('households')->onDelete('cascade'); // NULL = documento privado
            $table->foreignUuid('uploaded_by')->references('id')->on('users')->onDelete('cascade');
            $table->string('disk');
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->string('ai_path')->nullable(); // versión optimizada para IA
            $table->timestamps();

            $table->index(['household_id', 'sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economic_documents');
    }
};
