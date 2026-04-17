<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'customer_id']);
        });

        Schema::table('estimates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::table('estimates', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->after('account_id')
                ->constrained()
                ->restrictOnDelete();

            $table->index(['account_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'project_id']);
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('estimates', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->after('account_id')
                ->constrained()
                ->restrictOnDelete();

            $table->index(['account_id', 'customer_id']);
        });
    }
};
