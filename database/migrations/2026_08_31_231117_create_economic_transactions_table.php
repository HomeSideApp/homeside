<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economic_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('household_id')->nullable()->references('id')->on('households')->onDelete('cascade'); // NULL = cuenta privada del usuario
            $table->foreignUuid('created_by')->references('id')->on('users')->onDelete('cascade'); // dueño del registro
            $table->string('type'); // expense | income
            $table->string('scope'); // personal | shared (shared solo aplica con household_id)
            $table->string('title');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('place')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->foreignUuid('source_document_id')->nullable()->references('id')->on('economic_documents')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->foreignUuid('recurrence_parent_id')->nullable()->references('id')->on('economic_transactions')->nullOnDelete();
            $table->string('recurrence_frequency')->nullable();
            $table->unsignedSmallInteger('recurrence_interval')->nullable();
            $table->json('recurrence_weekdays')->nullable();
            $table->date('recurrence_ends_at')->nullable();
            $table->timestamp('recurrence_next_at')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'type']);
            $table->index(['household_id', 'scope']);
            $table->index(['household_id', 'occurred_at']);
            $table->index(['created_by', 'occurred_at']); // total personal: agregado casa + privado
            $table->unique(
                ['recurrence_parent_id', 'occurred_at'],
                'economic_transactions_recurrence_date_unique',
            );
            $table->index(
                ['recurrence_frequency', 'recurrence_next_at'],
                'economic_transactions_recurrence_due_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economic_transactions');
    }
};
