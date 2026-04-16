<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimate_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->index()->constrained()->restrictOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('category');
            $table->decimal('quantity', 12, 2);
            $table->string('unit');
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['estimate_id', 'key']);
            $table->index(['estimate_id', 'position']);
        });

        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn('line_item_prices');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->json('line_item_prices')->nullable();
        });

        Schema::dropIfExists('estimate_line_items');
    }
};
