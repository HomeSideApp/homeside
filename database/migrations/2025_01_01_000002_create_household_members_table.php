<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('household_id')->references('id')->on('households')->onDelete('cascade');
            $table->foreignUuid('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('role');
            $table->timestamp('joined_at');
            $table->timestamps();

            $table->unique(['household_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_members');
    }
};
