<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('household_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('display_name');
            $table->foreignUuid('preferred_record_id')->nullable();
            $table->foreignUuid('preferred_photo_record_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'display_name']);
            $table->index(['household_id', 'display_name']);
        });

        Schema::create('contact_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('household_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->boolean('sync_enabled')->default(true);
            $table->string('authentication_type', 32)->nullable();
            $table->text('encrypted_credentials')->nullable();
            $table->text('encrypted_tokens')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('provider_configuration')->nullable();
            $table->timestamp('last_sync_started_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status', 32)->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contact_collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contact_source_id')->constrained()->cascadeOnDelete();
            $table->string('remote_id')->nullable();
            $table->text('remote_href')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 32)->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('read_only')->default(true);
            $table->json('provider_metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['contact_source_id', 'remote_id']);
        });

        Schema::create('contact_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('remote_id')->nullable();
            $table->string('remote_uid')->nullable();
            $table->text('remote_href')->nullable();
            $table->string('remote_version')->nullable();
            $table->string('remote_etag')->nullable();
            $table->string('formatted_name');
            $table->string('given_name')->nullable();
            $table->string('family_name')->nullable();
            $table->string('additional_name')->nullable();
            $table->string('organization')->nullable();
            $table->string('job_title')->nullable();
            $table->date('birthday')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('remote_created_at')->nullable();
            $table->timestamp('remote_updated_at')->nullable();
            $table->timestamp('remote_deleted_at')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamps();
            $table->unique(['contact_source_id', 'remote_id']);
        });

        Schema::create('contact_collection_members', function (Blueprint $table) {
            $table->foreignUuid('contact_record_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_collection_id')->constrained()->cascadeOnDelete();
            $table->primary(['contact_record_id', 'contact_collection_id']);
        });

        Schema::create('contact_sync_states', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('contact_source_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_collection_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('cursor')->nullable();
            $table->string('cursor_type', 32)->nullable();
            $table->json('provider_state')->nullable();
            $table->timestamp('last_full_sync_at')->nullable();
            $table->timestamp('last_incremental_sync_at')->nullable();
            $table->timestamps();
            $table->unique('contact_collection_id');
        });

        foreach (['emails', 'phones', 'addresses', 'urls'] as $kind) {
            Schema::create('contact_'.$kind, function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('contact_record_id')->constrained()->cascadeOnDelete();
                $table->text('value');
                $table->string('type', 32)->nullable();
                $table->boolean('preferred')->default(false);
            });
        }

        Schema::create('contact_record_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('contact_record_id')->constrained()->cascadeOnDelete();
            $table->string('storage_path');
            $table->string('mime_type', 64);
            $table->unsignedInteger('size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('checksum', 64);
            $table->text('remote_uri')->nullable();
            $table->string('remote_etag')->nullable();
            $table->string('remote_version')->nullable();
            $table->timestamps();
            $table->unique('contact_record_id');
        });

        Schema::create('economic_transaction_contacts', function (Blueprint $table) {
            $table->foreignUuid('economic_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained()->restrictOnDelete();
            $table->string('role', 32);
            $table->primary(['economic_transaction_id', 'contact_id', 'role'], 'transaction_contact_role_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economic_transaction_contacts');
        Schema::dropIfExists('contact_record_photos');
        foreach (['urls', 'addresses', 'phones', 'emails'] as $kind) {
            Schema::dropIfExists('contact_'.$kind);
        }
        Schema::dropIfExists('contact_sync_states');
        Schema::dropIfExists('contact_collection_members');
        Schema::dropIfExists('contact_records');
        Schema::dropIfExists('contact_collections');
        Schema::dropIfExists('contact_sources');
        Schema::dropIfExists('contacts');
    }
};
