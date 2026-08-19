<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table partagée par les instruments négociables (`Market\Models\Instrument`) et les actifs
     * personnels (`Portfolio\Models\PersonalAsset`), discriminés par la colonne `type`.
     */
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->string('isin')->nullable();
            $table->string('ticker')->nullable();
            $table->string('name')->nullable();
            $table->string('type')->default('stock');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
