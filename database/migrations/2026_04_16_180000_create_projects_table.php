<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->string('name');

            $table->string('site_address_line_1')->nullable();
            $table->string('site_address_line_2')->nullable();
            $table->string('site_city')->nullable();
            $table->string('site_state')->nullable();
            $table->string('site_zip')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'customer_id']);
            $table->index(['account_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
