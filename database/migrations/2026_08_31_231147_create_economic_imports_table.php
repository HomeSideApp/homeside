<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economic_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('household_id')->nullable()->references('id')->on('households')->onDelete('cascade'); // NULL = import privado
            $table->foreignUuid('document_id')->references('id')->on('economic_documents')->onDelete('cascade');
            $table->foreignUuid('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreignUuid('ai_run_id')->nullable()->references('id')->on('ai_runs')->nullOnDelete(); // trazabilidad de la ejecución IA
            $table->string('status'); // pending|processing|ready_for_review|failed|confirmed|discarded
            $table->json('requested_sections');
            $table->json('extracted_payload')->nullable();
            $table->json('review_payload')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economic_imports');
    }
};
