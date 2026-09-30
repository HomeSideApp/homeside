<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_labels', function (Blueprint $table) {
            $table->foreignUuid('user_id')->nullable()->change();
            $table->foreignUuid('household_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['household_id', 'name']);
        });

        Schema::table('contact_contact_label', function (Blueprint $table) {
            $table->boolean('manual')->default(true);
            $table->boolean('imported')->default(false);
        });

        DB::table('contact_sync_states')
            ->whereIn('contact_source_id', DB::table('contact_sources')->where('provider', 'carddav')->select('id'))
            ->update([
                'cursor' => null,
                'cursor_type' => null,
                'provider_state' => json_encode(['etags' => []]),
                'last_full_sync_at' => null,
            ]);
    }

    public function down(): void
    {
        if (DB::table('contact_labels')->whereNotNull('household_id')->exists()) {
            throw new RuntimeException('Shared contact labels must be removed before rolling back.');
        }

        Schema::table('contact_contact_label', function (Blueprint $table) {
            $table->dropColumn(['manual', 'imported']);
        });
        Schema::table('contact_labels', function (Blueprint $table) {
            $table->dropUnique(['household_id', 'name']);
            $table->dropConstrainedForeignId('household_id');
            $table->foreignUuid('user_id')->nullable(false)->change();
        });
    }
};
