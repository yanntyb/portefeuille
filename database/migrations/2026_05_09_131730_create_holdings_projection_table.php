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
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->decimal('avg_cost', 14, 4)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->primary(['asset_id', 'wallet_id']);
            $table->index('user_id');
            $table->index(['user_id', 'wallet_id']);
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
