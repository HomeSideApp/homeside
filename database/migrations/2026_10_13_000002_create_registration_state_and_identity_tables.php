<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // The second, independent axis: approval answers "may this identity
            // exist?", account status answers "may it operate right now?".
            $table->string('account_status', 16)->default('active')->after('approval_status')->index();
        });

        Schema::create('registration_blocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('identifier_type', 32);
            $table->string('identifier_hash', 64);
            // `active_identifier_hash` mirrors `identifier_hash` while the block
            // is active (released_at IS NULL) and is NULLed on release.
            //
            // Why not uniqueness on (identifier_type, identifier_hash, released_at)?
            // SQL treats NULLs as distinct, so that triple permits an unbounded
            // number of active rows (all with released_at NULL) for one identity:
            // it guarantees nothing. A partial unique index would be the textbook
            // answer, but MySQL - the deployment target - has no partial indexes.
            // This nullable mirror is the portable equivalent of
            // `UNIQUE (identifier_type, identifier_hash) WHERE released_at IS NULL`:
            // active rows share a non-null mirror and collide, released rows all
            // carry NULL and therefore do not, so a NEW active block is allowed
            // after a release while two concurrent active blocks are impossible.
            $table->string('active_identifier_hash', 64)->nullable();
            // Key id lets a future rotation tell which key produced this hash.
            $table->string('identifier_key_id', 64)->nullable();
            $table->string('reason_code', 64);
            $table->text('reason_note')->nullable();
            $table->string('source', 32);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->unique(['identifier_type', 'active_identifier_hash'], 'registration_blocks_active_unique');
            $table->index(['identifier_type', 'identifier_hash'], 'registration_blocks_lookup_index');
        });

        Schema::create('registration_audits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Nullable on purpose: the historical record must outlive the user.
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32)->nullable();
            $table->string('channel', 64)->nullable();
            $table->string('origin', 64)->nullable();
            $table->string('invitation_reference', 128)->nullable();
            // Append-only: there is no updated_at by design.
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'action'], 'registration_audits_user_action_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_audits');
        Schema::dropIfExists('registration_blocks');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('account_status');
        });
    }
};
