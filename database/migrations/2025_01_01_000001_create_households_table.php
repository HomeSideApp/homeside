<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->string('color', 7)->nullable();
            $table->string('invite_code', 32)->unique();
            $table->json('settings')->nullable();
            $table->foreignUuid('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('active_household_id')->references('id')->on('households')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['active_household_id']);
        });

        Schema::dropIfExists('households');
    }
};
