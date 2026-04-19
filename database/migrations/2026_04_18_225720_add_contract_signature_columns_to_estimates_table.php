<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->text('contract_body_snapshot')->nullable()->after('approval_token');
            $table->timestamp('contract_signed_at')->nullable()->after('contract_body_snapshot');
            $table->string('contract_signed_name')->nullable()->after('contract_signed_at');
            $table->string('contract_signed_ip', 45)->nullable()->after('contract_signed_name');
            $table->string('contract_signed_user_agent', 500)->nullable()->after('contract_signed_ip');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn([
                'contract_body_snapshot',
                'contract_signed_at',
                'contract_signed_name',
                'contract_signed_ip',
                'contract_signed_user_agent',
            ]);
        });
    }
};
