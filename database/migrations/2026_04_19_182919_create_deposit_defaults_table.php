<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_defaults', function (Blueprint $table) {
            $table->id();
            $table->string('kind')->unique();
            $table->unsignedTinyInteger('material_deposit_percent');
            $table->unsignedTinyInteger('labor_deposit_percent');
            $table->timestamps();
        });

        DB::table('deposit_defaults')->insert([
            'kind' => 'default',
            'material_deposit_percent' => 100,
            'labor_deposit_percent' => 80,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_defaults');
    }
};
