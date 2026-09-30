<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_tag', function (Blueprint $table) {
            $table->foreignUuid('household_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['household_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_tag');
    }
};
