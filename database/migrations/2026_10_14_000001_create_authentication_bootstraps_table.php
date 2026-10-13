<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authentication_bootstraps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Cascade on delete: a bootstrap is a grant FOR a user, so it must
            // not outlive the row it authorises. Identity-level prohibition is
            // RegistrationBlock's job, not this transient credential's.
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            // The token is stored ONLY as a keyed digest, never in clear text.
            $table->string('token_hash', 64);
            $table->string('purpose', 64);
            $table->timestamp('expires_at');
            // NULL means still redeemable. The atomic consumption is a
            // conditional UPDATE on `consumed_at IS NULL`, so two simultaneous
            // requests can never both succeed.
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            // Lookup by digest is the only read path on a request.
            $table->index('token_hash', 'authentication_bootstraps_token_hash_index');
            // Invalidation sweeps target one user and purpose at a time.
            $table->index(['user_id', 'purpose'], 'authentication_bootstraps_user_purpose_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authentication_bootstraps');
    }
};
