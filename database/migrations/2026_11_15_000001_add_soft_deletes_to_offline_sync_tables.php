<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turn the offline write set into tombstones.
     *
     * A deletion must stay visible to a delta cursor, so the rows are kept with a
     * `deleted_at` marker instead of being removed. Every existing read keeps hiding
     * them through Eloquent's soft-delete scope, so no query had to change.
     */
    public function up(): void
    {
        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('list_items', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('economic_transactions', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Drop the tombstone markers. The deletion history is lost with them: any row
     * deleted while the column existed becomes a live row again.
     */
    public function down(): void
    {
        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('list_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('economic_transactions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
