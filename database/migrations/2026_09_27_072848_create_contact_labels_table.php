<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_labels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });

        Schema::create('contact_contact_label', function (Blueprint $table) {
            $table->foreignUuid('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_label_id')->constrained()->cascadeOnDelete();
            $table->primary(['contact_id', 'contact_label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_contact_label');
        Schema::dropIfExists('contact_labels');
    }
};
