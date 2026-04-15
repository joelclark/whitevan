<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            // Independent lifecycle from `status` (the AI extraction status).
            // Asset rendering can fail without flipping the main estimate to
            // failed — it's a separate failure domain.
            $table->string('floorplan_assets_status')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn('floorplan_assets_status');
        });
    }
};
