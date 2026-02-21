<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('account_type');
            $table->foreignId('security_id')->nullable()->constrained()->nullOnDelete();
            $table->string('broker')->nullable();
            $table->decimal('quantity', 12, 4)->nullable();
            $table->decimal('unit_price', 12, 4)->nullable();
            $table->decimal('fees', 10, 2)->default(0);
            $table->decimal('movement', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('date');
            $table->index('account_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
