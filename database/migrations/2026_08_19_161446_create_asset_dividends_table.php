<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Détachements de dividende par instrument, en montant par action. Six décimales et non
     * quatre comme les cours : un dividende trimestriel d'ETF se compte en centièmes de centime,
     * et l'arrondi dérive dès qu'on le multiplie par une position.
     */
    public function up(): void
    {
        Schema::create('asset_dividends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->date('ex_date');
            $table->decimal('amount_per_share', 12, 6);
            $table->timestamps();

            $table->unique(['asset_id', 'ex_date']);
            $table->index('ex_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_dividends');
    }
};
