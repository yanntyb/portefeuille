<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Charges ponctuelles datées (taxe foncière, copro, assurance, gestion, travaux, autre). */
    public function up(): void
    {
        Schema::create('property_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 12, 2);
            $table->string('category');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_expenses');
    }
};
