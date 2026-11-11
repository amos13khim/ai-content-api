<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 100)->charset('ascii')->collation('ascii_bin');
            $table->char('request_hash', 64);
            $table->json('input');
            $table->string('status', 20)->default('queued');
            $table->string('provider', 30);
            $table->string('model', 100);
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->text('result')->nullable();
            $table->string('error_code', 60)->nullable();
            $table->dateTime('available_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'created_at', 'id'], 'generations_history_index');
            $table->index(['user_id', 'status', 'created_at', 'id'], 'generations_status_history_index');
            $table->index(['status', 'available_at'], 'generations_ready_index');
            $table->index(['status', 'started_at'], 'generations_stale_index');
        });
        Schema::create('generation_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('generation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('status', 20)->default('started');
            $table->string('error_code', 60)->nullable();
            $table->string('request_id')->nullable();
            $table->string('response_id')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->unique(['generation_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_attempts');
        Schema::dropIfExists('generations');
    }
};
