<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->string('trade')->default('flooring')->after('customer_id');
            $table->index(['account_id', 'trade']);
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'trade']);
            $table->dropColumn('trade');
        });
    }
};
