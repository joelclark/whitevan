<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->string('title')->nullable();
            $table->string('pdf_path');
            $table->string('pdf_original_filename');
            $table->unsignedInteger('total_sqft')->nullable();
            $table->string('status')->default('processing');
            $table->json('interview_answers');
            $table->json('line_item_prices');
            $table->json('agent_errors');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'updated_at']);
            $table->index(['account_id', 'customer_id']);
            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimates');
    }
};
