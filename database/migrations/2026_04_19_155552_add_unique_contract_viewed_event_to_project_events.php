<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Enforce at-most-one `quote.contract_viewed` event per estimate at the
     * database layer. The controller check-then-insert was racy under two
     * concurrent GETs to the sign page; this partial unique index is what
     * actually guarantees the "first view only" invariant.
     *
     * Both SQLite and PostgreSQL support the `WHERE` predicate on unique
     * indexes, so the same statement works across dev and prod.
     */
    public function up(): void
    {
        DB::statement("
            CREATE UNIQUE INDEX project_events_contract_viewed_unique
            ON project_events (estimate_id)
            WHERE event = 'quote.contract_viewed' AND estimate_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS project_events_contract_viewed_unique');
    }
};
