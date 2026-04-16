<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->string('quote_status')->nullable()->after('status');
            $table->string('quote_token')->nullable()->unique()->after('quote_status');
            $table->timestamp('quote_sent_at')->nullable()->after('quote_token');
            $table->timestamp('quote_customer_viewed_at')->nullable()->after('quote_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropUnique(['quote_token']);
            $table->dropColumn(['quote_status', 'quote_token', 'quote_sent_at', 'quote_customer_viewed_at']);
        });
    }
};
