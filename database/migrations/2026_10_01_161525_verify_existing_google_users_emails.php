<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('google_identities')
                    ->whereColumn('google_identities.user_id', 'users.id');
            })
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Google verified these addresses; rolling back must not mark them as unverified.
    }
};
