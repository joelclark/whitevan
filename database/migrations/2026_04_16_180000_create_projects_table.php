<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->string('name');

            $table->string('site_address_line_1')->nullable();
            $table->string('site_address_line_2')->nullable();
            $table->string('site_city')->nullable();
            $table->string('site_state')->nullable();
            $table->string('site_zip')->nullable();

            $table->text('notes')->nullable();

            // Denormalized sort key for the /projects workspace list. Not
            // `updated_at` because we don't want "Laravel touched this row" to
            // be conflated with "something meaningful happened on this job".
            // Bumped explicitly via Project::recordActivity() from controllers
            // and jobs; see CLAUDE.md for the bump-site policy.
            $table->timestamp('last_activity_at')->useCurrent();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'customer_id']);
            $table->index(['account_id', 'last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
