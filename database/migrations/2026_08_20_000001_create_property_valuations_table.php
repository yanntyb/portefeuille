<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Estimations datées de la valeur d'un bien, saisies à la main. La dernière fait foi. */
    public function up(): void
    {
        Schema::create('property_valuations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('value', 12, 2);
            $table->timestamps();

            $table->unique(['property_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_valuations');
    }
};
