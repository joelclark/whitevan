<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimate_line_items', function (Blueprint $table) {
            $table->timestamp('deprecated_at')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('estimate_line_items', function (Blueprint $table) {
            $table->dropColumn('deprecated_at');
        });
    }
};
