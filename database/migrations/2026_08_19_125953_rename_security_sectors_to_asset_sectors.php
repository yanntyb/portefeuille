<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Solde le renommage `securities` → `assets` : la table des secteurs et les index hérités
     * de `security_prices` portaient encore l'ancien vocabulaire.
     *
     * L'index composite `security_prices_security_id_date_index` fait doublon avec
     * `asset_prices_asset_id_date_index` sur les mêmes colonnes, il n'est pas recréé.
     */
    public function up(): void
    {
        Schema::rename('security_sectors', 'asset_sectors');

        Schema::table('asset_sectors', function (Blueprint $table): void {
            $table->dropUnique('security_sectors_security_id_sector_unique');
            $table->unique(['asset_id', 'sector']);
        });

        Schema::table('asset_prices', function (Blueprint $table): void {
            $table->dropIndex('security_prices_date_index');
            $table->dropIndex('security_prices_security_id_date_index');
            $table->dropUnique('security_prices_security_id_date_unique');
            $table->index('date');
            $table->unique(['asset_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_prices', function (Blueprint $table): void {
            $table->dropUnique('asset_prices_asset_id_date_unique');
            $table->dropIndex('asset_prices_date_index');
        });

        Schema::table('asset_prices', function (Blueprint $table): void {
            $table->unique(['asset_id', 'date'], 'security_prices_security_id_date_unique');
            $table->index(['asset_id', 'date'], 'security_prices_security_id_date_index');
            $table->index('date', 'security_prices_date_index');
        });

        Schema::table('asset_sectors', function (Blueprint $table): void {
            $table->dropUnique('asset_sectors_asset_id_sector_unique');
            $table->unique(['asset_id', 'sector'], 'security_sectors_security_id_sector_unique');
        });

        Schema::rename('asset_sectors', 'security_sectors');
    }
};
