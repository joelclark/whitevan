<?php

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
        Schema::table('estimate_line_items', function (Blueprint $table) {
            $table->boolean('price_prefilled')->default(false)->after('unit_price');

            // Cross-estimate price lookup probes by key (+ kind); existing
            // indexes lead with estimate_id and don't serve a key-first probe.
            $table->index(['key', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estimate_line_items', function (Blueprint $table) {
            $table->dropIndex(['key', 'kind']);
            $table->dropColumn('price_prefilled');
        });
    }
};
