<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('company')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'last_accessed_at']);
            $table->index(['account_id', 'last_name', 'first_name']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX customers_account_id_email_unique
                ON customers (account_id, email)
                WHERE deleted_at IS NULL AND email IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
