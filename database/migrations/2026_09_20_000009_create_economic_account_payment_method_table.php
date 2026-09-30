<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Allow a single economic account to be used with several payment methods.
     *
     * A bank account may be charged by card, Bizum or PayPal, so the payment method stops being a
     * single column on the account and becomes a many-to-many relation. Existing rows are migrated
     * into the new pivot table before the legacy column is dropped, so no account loses its method.
     *
     * @return void This migration method does not return a value.
     */
    public function up(): void
    {
        Schema::create('economic_account_payment_method', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('economic_account_id')->references('id')->on('economic_accounts')->cascadeOnDelete();
            $table->foreignUuid('payment_method_id')->references('id')->on('payment_methods')->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['economic_account_id', 'payment_method_id'],
                'economic_account_payment_method_unique',
            );
            $table->index('payment_method_id', 'economic_account_payment_method_method_index');
        });

        $now = now();

        DB::table('economic_accounts')
            ->whereNotNull('payment_method_id')
            ->orderBy('id')
            ->each(function (object $account) use ($now): void {
                DB::table('economic_account_payment_method')->insert([
                    'id' => (string) Str::uuid(),
                    'economic_account_id' => $account->id,
                    'payment_method_id' => $account->payment_method_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

        Schema::table('economic_accounts', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn('payment_method_id');
        });
    }

    /**
     * Restore the single payment method column and move the first relation back into it.
     *
     * @return void This migration method does not return a value.
     */
    public function down(): void
    {
        Schema::table('economic_accounts', function (Blueprint $table) {
            $table->foreignUuid('payment_method_id')->nullable()->after('user_id')
                ->references('id')->on('payment_methods')->restrictOnDelete();
        });

        DB::table('economic_account_payment_method')
            ->orderBy('economic_account_id')
            ->each(function (object $pivot): void {
                DB::table('economic_accounts')
                    ->where('id', $pivot->economic_account_id)
                    ->whereNull('payment_method_id')
                    ->update(['payment_method_id' => $pivot->payment_method_id]);
            });

        Schema::dropIfExists('economic_account_payment_method');
    }
};
