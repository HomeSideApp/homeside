<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes supporting the import listing filters.
     *
     * The import index filters by household or owner together with the lifecycle status, so the
     * composite indexes keep those listings efficient as the history grows.
     *
     * @return void This migration method does not return a value.
     */
    public function up(): void
    {
        Schema::table('economic_imports', function (Blueprint $table) {
            $table->index(['household_id', 'status', 'created_at'], 'economic_imports_household_status_index');
            $table->index(['created_by', 'status', 'created_at'], 'economic_imports_creator_status_index');
        });
    }

    /**
     * Remove the import listing indexes.
     *
     * @return void This migration method does not return a value.
     */
    public function down(): void
    {
        Schema::table('economic_imports', function (Blueprint $table) {
            $table->dropIndex('economic_imports_household_status_index');
            $table->dropIndex('economic_imports_creator_status_index');
        });
    }
};
