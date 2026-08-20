<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baux à loyer constant. `end_date` nulle = bail en cours. Une révision de loyer clôt le
     * bail et en ouvre un autre ; la vacance locative est un trou entre deux baux, pas une ligne.
     */
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->decimal('monthly_rent', 8, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
