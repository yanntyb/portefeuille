<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // transactions.wallet_id — used for wallet-scoped queries
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('wallet_id');
        });

        // security_prices (security_id, date) — used by VolatilityCalculator::forWallet()
        // which queries and orders by date per security
        Schema::table('security_prices', function (Blueprint $table) {
            $table->index(['security_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['wallet_id']);
        });

        Schema::table('security_prices', function (Blueprint $table) {
            $table->dropIndex(['security_id', 'date']);
        });
    }
};
