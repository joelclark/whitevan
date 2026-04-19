<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')->constrained()->restrictOnDelete();

            // restrictOnDelete on project_id: we never want a silent delete
            // to drop audit history. Soft-delete is the normal path for
            // projects and leaves the FK intact.
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('estimate_id')->nullable()->constrained()->nullOnDelete();

            $table->string('event');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_type');
            $table->boolean('customer_visible')->default(false);
            $table->json('metadata')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Primary contractor-facing timeline query.
            $table->index(['account_id', 'project_id', 'created_at']);
            // Hot path for the deferred customer-facing view — cheap to add
            // up-front, painful to backfill under load later.
            $table->index(['project_id', 'customer_visible', 'created_at']);
            // Cross-project metric queries (e.g. "how many quotes sent this
            // week").
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_events');
    }
};
