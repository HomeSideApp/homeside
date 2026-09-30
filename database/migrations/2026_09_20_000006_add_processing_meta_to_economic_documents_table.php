<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the adjustments applied when generating the AI version of a document.
     *
     * Keeping the applied processing makes the generated analysis reproducible and lets the
     * review screen tell the user that the analysis used a cropped or adjusted image.
     *
     * @return void This migration method does not return a value.
     */
    public function up(): void
    {
        Schema::table('economic_documents', function (Blueprint $table) {
            $table->json('processing_meta')->nullable()->after('ai_path');
        });
    }

    /**
     * Remove the stored image processing metadata.
     *
     * @return void This migration method does not return a value.
     */
    public function down(): void
    {
        Schema::table('economic_documents', function (Blueprint $table) {
            $table->dropColumn('processing_meta');
        });
    }
};
