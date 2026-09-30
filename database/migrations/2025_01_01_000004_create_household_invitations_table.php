<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('household_id')->references('id')->on('households')->onDelete('cascade');
            $table->foreignUuid('invited_by')->references('id')->on('users')->onDelete('cascade');
            $table->string('email');
            $table->string('status')->default('pending');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_invitations');
    }
};
