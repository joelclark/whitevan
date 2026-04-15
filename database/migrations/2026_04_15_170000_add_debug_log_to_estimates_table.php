<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            // Stores the last agent invocation's instructions, schema,
            // prompt, and response (or error body). Purely for operator
            // debugging — never exposed in list views, only on the
            // estimate edit page behind a collapsed panel.
            $table->json('debug_log')->nullable()->after('agent_errors');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn('debug_log');
        });
    }
};
