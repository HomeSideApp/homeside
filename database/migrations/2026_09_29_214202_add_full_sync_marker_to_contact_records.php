<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_records', function (Blueprint $table): void {
            $table->uuid('last_seen_full_sync_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('contact_records', function (Blueprint $table): void {
            $table->dropColumn('last_seen_full_sync_id');
        });
    }
};
