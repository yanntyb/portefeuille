<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop composite index from 2026_05_08_012812 before rename
        Schema::table('asset_prices', function (Blueprint $table): void {
            $table->dropIndex(['security_id', 'date']);
        });

        Schema::table('asset_prices', function (Blueprint $table): void {
            $table->dropForeign(['security_id']); // no-op on SQLite, works on MySQL
            $table->renameColumn('security_id', 'asset_id');
        });

        Schema::table('asset_prices', function (Blueprint $table): void {
            $table->foreign('asset_id')->references('id')->on('securities')->cascadeOnDelete();
            $table->index(['asset_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('asset_prices', function (Blueprint $table): void {
            $table->dropIndex(['asset_id', 'date']);
        });

        Schema::table('asset_prices', function (Blueprint $table): void {
            $table->dropForeign(['asset_id']);
            $table->renameColumn('asset_id', 'security_id');
        });

        Schema::table('asset_prices', function (Blueprint $table): void {
            $table->foreign('security_id')->references('id')->on('securities')->cascadeOnDelete();
            $table->index(['security_id', 'date']);
        });
    }
};
