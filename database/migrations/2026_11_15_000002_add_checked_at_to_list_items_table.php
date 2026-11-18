<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record when an item was checked off.
     *
     * Two offline clients toggling the same item cannot be ordered by `is_checked`
     * alone, so the shared toggle resolves by most-recent check instead.
     */
    public function up(): void
    {
        Schema::table('list_items', function (Blueprint $table) {
            $table->timestamp('checked_at')->nullable()->after('is_checked');
        });
    }

    /**
     * Drop the check timestamp.
     */
    public function down(): void
    {
        Schema::table('list_items', function (Blueprint $table) {
            $table->dropColumn('checked_at');
        });
    }
};
