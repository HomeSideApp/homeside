<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('household_members', function (Blueprint $table): void {
            $table->foreignUuid('contact_id')->nullable()->after('joined_at')->constrained('contacts')->nullOnDelete();
            $table->index(['household_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::table('household_members', function (Blueprint $table): void {
            $table->dropIndex(['household_id', 'contact_id']);
            $table->dropConstrainedForeignId('contact_id');
        });
    }
};
