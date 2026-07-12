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
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('quantity', 20, 8)->nullable()->change();
        });

        Schema::table('holdings_projection', function (Blueprint $table) {
            $table->decimal('quantity', 20, 8)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('quantity', 12, 4)->nullable()->change();
        });

        Schema::table('holdings_projection', function (Blueprint $table) {
            $table->decimal('quantity', 14, 4)->change();
        });
    }
};
