<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biens immobiliers détenus. `acquisition_fees` : notaire, agence, dossier — tout ce qui
     * s'ajoute au prix pour former le coût d'acquisition total.
     */
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->date('acquisition_date');
            $table->decimal('acquisition_price', 12, 2);
            $table->decimal('acquisition_fees', 12, 2)->default(0);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
