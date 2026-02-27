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
        Schema::create('allocation_profile_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('security_id')->constrained()->cascadeOnDelete();
            $table->decimal('target_percentage', 5, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allocation_profile_items');
    }
};
