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
        Schema::create('security_sectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_id')->constrained()->cascadeOnDelete();
            $table->string('sector');
            $table->decimal('weight', 8, 6);
            $table->timestamps();

            $table->unique(['security_id', 'sector']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_sectors');
    }
};
