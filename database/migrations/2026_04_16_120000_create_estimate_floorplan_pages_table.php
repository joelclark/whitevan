<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimate_floorplan_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('page');
            $table->string('image_path');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');

            $table->timestamps();

            $table->unique(['estimate_id', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_floorplan_pages');
    }
};
