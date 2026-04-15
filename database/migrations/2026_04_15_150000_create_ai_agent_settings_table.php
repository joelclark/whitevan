<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent_settings', function (Blueprint $table) {
            $table->id();
            $table->string('kind')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->text('system_prompt');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_settings');
    }
};
