<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('approval_status', 16)->default('approved')->index();
            $table->timestamp('approved_at')->nullable();
        });

        Schema::create('google_identities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('google_sub')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_identities');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['approval_status', 'approved_at']);
        });
    }
};
