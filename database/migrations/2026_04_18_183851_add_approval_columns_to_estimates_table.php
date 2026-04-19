<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->string('approval_token')->nullable()->unique()->after('quote_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropUnique(['approval_token']);
            $table->dropColumn(['approval_token']);
        });
    }
};
