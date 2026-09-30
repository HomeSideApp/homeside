<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economic_transaction_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->references('id')->on('economic_transactions')->onDelete('cascade');
            $table->string('name');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('unit_amount_minor');
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('tax_amount_minor')->nullable();
            $table->unsignedBigInteger('total_minor');
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['transaction_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economic_transaction_items');
    }
};
