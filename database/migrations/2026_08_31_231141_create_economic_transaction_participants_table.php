<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economic_transaction_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->references('id')->on('economic_transactions')->onDelete('cascade');
            $table->foreignUuid('household_member_id')->references('id')->on('household_members')->onDelete('cascade');
            $table->string('split_type'); // equal | fixed | percentage
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedSmallInteger('percentage')->nullable();
            $table->timestamps();

            // Nombre corto: el identificador por defecto supera el límite de MySQL (64)
            $table->unique(['transaction_id', 'household_member_id'], 'et_participants_tx_member_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economic_transaction_participants');
    }
};
