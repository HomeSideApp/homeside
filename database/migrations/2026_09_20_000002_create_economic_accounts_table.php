<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the private economic accounts table.
     *
     * Accounts always belong to a single user: even when they are used to pay a household
     * expense, the association is never disclosed to other household members.
     *
     * @return void This migration method does not return a value.
     */
    public function up(): void
    {
        Schema::create('economic_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->onDelete('cascade'); // siempre privada del usuario
            $table->foreignUuid('payment_method_id')->references('id')->on('payment_methods')->restrictOnDelete();
            $table->string('name');
            $table->string('currency', 10); // ampliada a 10 para admitir BTC/ETH
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->bigInteger('initial_balance_minor')->default(0); // admite negativos (deudas)
            $table->date('initial_balance_at')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->default('#6366F1');
            $table->string('last_four_digits', 4)->nullable();
            $table->boolean('include_in_totals')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name', 'currency']);
            $table->index(['user_id', 'archived_at']);
        });
    }

    /**
     * Drop the private economic accounts table.
     *
     * @return void This migration method does not return a value.
     */
    public function down(): void
    {
        Schema::dropIfExists('economic_accounts');
    }
};
