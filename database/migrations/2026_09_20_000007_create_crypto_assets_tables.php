<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the crypto asset catalogue and its price snapshots.
     *
     * Assets describe the supported symbols together with their decimal precision, while the
     * snapshots store spot prices used to value crypto accounts without hitting the market API
     * during a user request.
     *
     * @return void This migration method does not return a value.
     */
    public function up(): void
    {
        Schema::create('crypto_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('symbol', 10)->unique();
            $table->string('name');
            $table->string('coingecko_id');
            $table->unsignedTinyInteger('decimal_places');
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('crypto_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('crypto_asset_id')->references('id')->on('crypto_assets')->cascadeOnDelete();
            $table->string('quote_currency', 3)->default('EUR');
            $table->decimal('price', 24, 8);
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['crypto_asset_id', 'quote_currency', 'fetched_at'], 'crypto_price_snapshot_unique');
            $table->index(['crypto_asset_id', 'quote_currency', 'fetched_at'], 'crypto_price_lookup_index');
        });
    }

    /**
     * Drop the crypto asset catalogue and its price snapshots.
     *
     * @return void This migration method does not return a value.
     */
    public function down(): void
    {
        Schema::dropIfExists('crypto_prices');
        Schema::dropIfExists('crypto_assets');
    }
};
