<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the economic transaction attachments table.
     *
     * Attachments are evidence attached to an already created movement (receipt photos,
     * receipts of payment), which is a different concern from `economic_documents`, whose
     * purpose is being analysed by the AI before a transaction exists.
     *
     * @return void This migration method does not return a value.
     */
    public function up(): void
    {
        Schema::create('economic_transaction_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->references('id')->on('economic_transactions')->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')->references('id')->on('users')->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['transaction_id', 'sort_order']);
        });
    }

    /**
     * Drop the economic transaction attachments table.
     *
     * @return void This migration method does not return a value.
     */
    public function down(): void
    {
        Schema::dropIfExists('economic_transaction_attachments');
    }
};
