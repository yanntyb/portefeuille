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
        Schema::table('security_sectors', function (Blueprint $table): void {
            $table->dropForeign(['security_id']); // no-op on SQLite
            $table->renameColumn('security_id', 'asset_id');
        });

        Schema::table('security_sectors', function (Blueprint $table): void {
            $table->foreign('asset_id')->references('id')->on('securities')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_sectors', function (Blueprint $table): void {
            $table->dropForeign(['asset_id']);
            $table->renameColumn('asset_id', 'security_id');
        });

        Schema::table('security_sectors', function (Blueprint $table): void {
            $table->foreign('security_id')->references('id')->on('securities')->cascadeOnDelete();
        });
    }
};
