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
        Schema::create('holdings_projection', function (Blueprint $table) {
            $table->foreignId('asset_id')->constrained('securities')->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('avg_cost', 10, 4)->nullable();
            $table->timestamps();

            $table->primary(['asset_id', 'wallet_id']);
            $table->index(['user_id']);
            $table->index(['wallet_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holdings_projection');
    }
};
