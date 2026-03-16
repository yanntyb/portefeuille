<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('wallet_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });

        // Créer un wallet par (user_id, account_type) unique, puis lier les transactions
        $groups = DB::table('transactions')
            ->select('user_id', 'account_type')
            ->distinct()
            ->get();

        foreach ($groups as $group) {
            $walletName = match ($group->account_type) {
                'pea' => 'PEA',
                'cto' => 'CTO',
                'livret' => 'Livret',
                default => ucfirst($group->account_type),
            };

            $walletId = DB::table('wallets')->insertGetId([
                'user_id' => $group->user_id,
                'name' => $walletName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('transactions')
                ->where('user_id', $group->user_id)
                ->where('account_type', $group->account_type)
                ->update(['wallet_id' => $walletId]);
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_account_type_index');
            $table->dropColumn('account_type');
            $table->foreignId('wallet_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('account_type')->nullable()->after('user_id');
        });

        // Backfill inverse : wallet.name → account_type
        $wallets = DB::table('wallets')->get(['id', 'name']);

        foreach ($wallets as $wallet) {
            $accountType = match ($wallet->name) {
                'PEA' => 'pea',
                'CTO' => 'cto',
                'Livret' => 'livret',
                default => strtolower($wallet->name),
            };

            DB::table('transactions')
                ->where('wallet_id', $wallet->id)
                ->update(['account_type' => $accountType]);
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('account_type')->nullable(false)->change();
            $table->index('account_type');
            $table->dropForeign(['wallet_id']);
            $table->dropColumn('wallet_id');
        });
    }
};
