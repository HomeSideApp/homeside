<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the payment methods catalogue table.
     *
     * Global catalogue rows (seeded by the application) keep `user_id` as null and are
     * read-only for regular users; rows with a `user_id` belong to that user only.
     *
     * @return void This migration method does not return a value.
     */
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->references('id')->on('users')->onDelete('cascade'); // NULL = método global
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->string('color')->default('#9E9E9E');
            $table->string('kind')->default('other'); // cash | card | bank_transfer | digital_wallet | crypto | cheque | other
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Drop the payment methods catalogue table.
     *
     * @return void This migration method does not return a value.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
