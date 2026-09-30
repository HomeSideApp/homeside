<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link crypto accounts to their asset catalogue entry.
     *
     * The column is only set for accounts whose payment method is a crypto wallet, and it nulls out
     * when the asset is removed so the account history survives.
     *
     * @return void This migration method does not return a value.
     */
    public function up(): void
    {
        Schema::table('economic_accounts', function (Blueprint $table) {
            $table->foreignUuid('crypto_asset_id')->nullable()->after('currency')
                ->references('id')->on('crypto_assets')->nullOnDelete();
        });
    }

    /**
     * Remove the crypto asset association from economic accounts.
     *
     * @return void This migration method does not return a value.
     */
    public function down(): void
    {
        Schema::table('economic_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('crypto_asset_id');
        });
    }
};
