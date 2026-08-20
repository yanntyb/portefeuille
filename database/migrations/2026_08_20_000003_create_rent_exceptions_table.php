<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Loyer effectif d'un mois qui dévie du bail : `0` = impayé total, un montant partiel est
     * possible. `month` porte le premier jour du mois. Absence de ligne = loyer plein.
     */
    public function up(): void
    {
        Schema::create('rent_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->decimal('amount_override', 8, 2);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['lease_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_exceptions');
    }
};
