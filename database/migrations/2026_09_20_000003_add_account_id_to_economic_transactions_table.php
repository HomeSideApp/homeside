<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attach an optional private account to each economic transaction.
     *
     * The column is nullable and nulls out when the account is deleted, so removing an
     * account never blocks or deletes the movement history that referenced it.
     *
     * @return void This migration method does not return a value.
     */
    public function up(): void
    {
        Schema::table('economic_transactions', function (Blueprint $table) {
            $table->foreignUuid('account_id')->nullable()->after('currency')
                ->references('id')->on('economic_accounts')->nullOnDelete();

            $table->index(['account_id', 'occurred_at']);
        });
    }

    /**
     * Remove the optional account association from economic transactions.
     *
     * @return void This migration method does not return a value.
     */
    public function down(): void
    {
        Schema::table('economic_transactions', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'occurred_at']);
            $table->dropConstrainedForeignId('account_id');
        });
    }
};
