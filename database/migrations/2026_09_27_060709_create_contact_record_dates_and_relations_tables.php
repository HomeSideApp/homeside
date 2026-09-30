<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_records', function (Blueprint $table) {
            $table->string('nickname')->nullable();
        });

        Schema::create('contact_record_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('contact_record_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('label')->nullable();
            $table->string('value', 64);
            $table->string('value_type', 16)->default('date');
        });

        Schema::create('contact_record_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('contact_record_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->foreignUuid('related_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('name')->nullable();
            $table->text('external_value')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_record_relations');
        Schema::dropIfExists('contact_record_dates');

        Schema::table('contact_records', function (Blueprint $table) {
            $table->dropColumn('nickname');
        });
    }
};
