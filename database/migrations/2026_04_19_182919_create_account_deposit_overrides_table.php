<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deposit_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('material_deposit_percent')->nullable();
            $table->unsignedTinyInteger('labor_deposit_percent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deposit_overrides');
    }
};
