<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `quantity` accepte 8 décimales pour les fractions de crypto, `unit_price` 4 comme les
     * cotations. `asset_id` et `user_id` passent à NULL à la suppression : une transaction
     * orpheline reste préférable à une perte d'historique.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('buy')->index();
            $table->string('broker')->nullable();
            $table->decimal('quantity', 20, 8)->nullable();
            $table->decimal('unit_price', 12, 4)->nullable();
            $table->decimal('fees', 10, 2)->default(0);
            $table->decimal('realized_gain', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('wallet_id');
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
