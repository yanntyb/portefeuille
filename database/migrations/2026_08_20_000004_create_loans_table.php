<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prêts amortissables à la française. `annual_rate` en fraction (`0.024` = 2,4 %/an).
     * L'échéancier n'est jamais stocké : recalculé depuis ces paramètres.
     */
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->decimal('principal', 12, 2);
            $table->decimal('annual_rate', 8, 5);
            $table->unsignedInteger('term_months');
            $table->date('start_date');
            $table->decimal('monthly_insurance', 8, 2)->default(0);
            $table->timestamps();

            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
