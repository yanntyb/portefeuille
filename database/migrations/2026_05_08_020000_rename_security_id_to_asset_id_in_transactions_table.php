<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['security_id']);
            $table->dropIndex(['security_id']);
            $table->renameColumn('security_id', 'asset_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('asset_id')->references('id')->on('securities')->nullOnDelete();
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['asset_id']);
            $table->dropIndex(['asset_id']);
            $table->renameColumn('asset_id', 'security_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('security_id')->references('id')->on('securities')->nullOnDelete();
            $table->index('security_id');
        });
    }
};
