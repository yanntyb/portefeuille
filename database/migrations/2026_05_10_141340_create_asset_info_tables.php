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
        Schema::create('stock_asset_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('securities')->cascadeOnDelete();
            $table->string('isin');
            $table->string('ticker');
            $table->timestamps();
            $table->unique(['asset_id']);
        });

        Schema::create('etf_asset_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('securities')->cascadeOnDelete();
            $table->string('isin');
            $table->string('ticker');
            $table->timestamps();
            $table->unique(['asset_id']);
        });

        Schema::create('bond_asset_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('securities')->cascadeOnDelete();
            $table->string('isin');
            $table->string('ticker')->nullable();
            $table->timestamps();
            $table->unique(['asset_id']);
        });

        Schema::create('crypto_asset_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('securities')->cascadeOnDelete();
            $table->string('ticker');
            $table->timestamps();
            $table->unique(['asset_id']);
        });

        Schema::create('realestate_asset_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('securities')->cascadeOnDelete();
            $table->string('isin')->nullable();
            $table->timestamps();
            $table->unique(['asset_id']);
        });

        Schema::create('savings_asset_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('securities')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['asset_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('savings_asset_infos');
        Schema::dropIfExists('realestate_asset_infos');
        Schema::dropIfExists('crypto_asset_infos');
        Schema::dropIfExists('bond_asset_infos');
        Schema::dropIfExists('etf_asset_infos');
        Schema::dropIfExists('stock_asset_infos');
    }
};
