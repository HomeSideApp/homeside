<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economic_transaction_taxes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->references('id')->on('economic_transactions')->onDelete('cascade');
            $table->string('name'); // texto libre: "IVA", "VAT", "GST", "Sales tax", "IGV"...
            $table->unsignedSmallInteger('rate'); // porcentaje * 100, ej: 2100 = 21%
            $table->unsignedBigInteger('taxable_base_minor');
            $table->unsignedBigInteger('tax_amount_minor');
            $table->timestamps();

            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economic_transaction_taxes');
    }
};
