<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL supports both foreign keys with the composite unique index, and refuses to drop
        // an index that a constraint still depends on. Dedicated indexes take over the support of
        // both keys so the constraints can be dropped before the old unique index goes away.
        Schema::table('economic_transaction_participants', function (Blueprint $table) {
            $table->index('transaction_id', 'et_participants_tx_index');
            $table->index('household_member_id', 'et_participants_member_index');
        });

        Schema::table('economic_transaction_participants', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->dropForeign(['household_member_id']);
        });

        Schema::table('economic_transaction_participants', function (Blueprint $table) {
            $table->dropUnique('et_participants_tx_member_unique');
        });

        Schema::table('economic_transaction_participants', function (Blueprint $table) {
            $table->uuid('household_member_id')->nullable()->change();
            $table->foreignUuid('contact_id')->nullable()->after('household_member_id')
                ->constrained('contacts')->nullOnDelete();
            $table->string('display_name', 120)->nullable()->after('contact_id');
            $table->unsignedSmallInteger('share_count')->default(1)->after('display_name');
        });

        Schema::table('economic_transaction_participants', function (Blueprint $table) {
            // Virtual (not stored): MySQL refuses to create a foreign key with ON DELETE CASCADE
            // on a base column of a *stored* generated column (error 1215), and
            // household_member_id must keep its cascade. InnoDB indexes virtual columns, so the
            // unique index below stays portable. The expression mirrors
            // EconomicTransactionParticipantData::identityKey().
            $table->string('participant_key', 150)
                ->virtualAs("COALESCE(household_member_id, CONCAT('ext:', LOWER(TRIM(display_name))), 'anon')")
                ->after('share_count');

            // Short name: the default identifier exceeds the MySQL limit (64).
            $table->unique(['transaction_id', 'participant_key'], 'et_participants_tx_key_unique');
        });

        Schema::table('economic_transaction_participants', function (Blueprint $table) {
            $table->foreign('transaction_id')
                ->references('id')->on('economic_transactions')
                ->cascadeOnDelete();
            $table->foreign('household_member_id')
                ->references('id')->on('household_members')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('economic_transaction_participants', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->dropForeign(['household_member_id']);
        });

        Schema::table('economic_transaction_participants', function (Blueprint $table) {
            $table->dropUnique('et_participants_tx_key_unique');
            $table->dropForeign(['contact_id']);
        });

        Schema::table('economic_transaction_participants', function (Blueprint $table) {
            $table->dropColumn(['participant_key', 'contact_id', 'display_name', 'share_count']);
        });

        // The previous schema cannot represent external portions: a row without a member
        // identity is impossible there, so those rows are removed before the column is required.
        DB::table('economic_transaction_participants')->whereNull('household_member_id')->delete();

        Schema::table('economic_transaction_participants', function (Blueprint $table) {
            $table->uuid('household_member_id')->nullable(false)->change();
            $table->unique(['transaction_id', 'household_member_id'], 'et_participants_tx_member_unique');
            $table->dropIndex('et_participants_tx_index');
            $table->dropIndex('et_participants_member_index');
        });

        Schema::table('economic_transaction_participants', function (Blueprint $table) {
            $table->foreign('transaction_id')
                ->references('id')->on('economic_transactions')
                ->cascadeOnDelete();
            $table->foreign('household_member_id')
                ->references('id')->on('household_members')
                ->cascadeOnDelete();
        });
    }
};
