<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('job_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('user_id')->nullable()->index()->constrained('users')->onDelete('set null');
            $table->string('job_class')->index();
            $table->string('queue')->index();
            $table->string('connection');
            $table->enum('status', ['processing', 'processed', 'failed'])->index();
            $table->json('payload')->nullable();
            $table->text('exception')->nullable();
            $table->longText('stack_trace')->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedBigInteger('memory_start')->nullable();
            $table->unsignedBigInteger('memory_end')->nullable();
            $table->unsignedBigInteger('memory_peak')->nullable();
            $table->float('cpu_user')->nullable();
            $table->float('cpu_system')->nullable();
            $table->foreignUuid('original_job_run_id')->nullable()->index()->constrained('job_runs')->onDelete('set null');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_runs');
    }
};
