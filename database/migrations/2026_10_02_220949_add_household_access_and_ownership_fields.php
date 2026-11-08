<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['household_members', 'household_invitations', 'household_invite_links'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('access_profile')->nullable();
                $table->json('module_access')->nullable();
            });
        }

        Schema::table('households', function (Blueprint $table): void {
            $table->dropForeign(['created_by']);
        });
        Schema::table('households', function (Blueprint $table): void {
            $table->uuid('created_by')->nullable()->change();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::table('users', function (Blueprint $table): void {
            // NULLABLE tri-state added by the registration-normalisation work:
            // null delegates to Policy + EntitlementGate, false is an explicit
            // prohibition. There is deliberately no `true` state; a grant must
            // come from the entitlement gate, never from a permissive default.
            $table->boolean('can_create_households_override')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('can_create_households_override'));
        Schema::table('households', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropForeign(['created_by']);
        });
        Schema::table('households', function (Blueprint $table): void {
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });
        foreach (['household_members', 'household_invitations', 'household_invite_links'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn(['access_profile', 'module_access']));
        }
    }
};
