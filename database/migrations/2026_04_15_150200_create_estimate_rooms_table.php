<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimate_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->unsignedSmallInteger('page');
            $table->unsignedInteger('sqft');
            $table->unsignedInteger('linear_feet');
            $table->unsignedSmallInteger('position');

            $table->timestamps();

            $table->index(['estimate_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_rooms');
    }
};
