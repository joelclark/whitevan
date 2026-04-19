<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimate_line_items', function (Blueprint $table) {
            $table->string('kind')->default('labor')->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('estimate_line_items', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
