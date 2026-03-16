<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allocation_profiles', function (Blueprint $table) {
            $table->foreignId('wallet_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });

        // Backfill wallet_id depuis account_type
        $profiles = DB::table('allocation_profiles')
            ->whereNotNull('account_type')
            ->get(['id', 'user_id', 'account_type']);

        foreach ($profiles as $profile) {
            $walletName = match ($profile->account_type) {
                'pea' => 'PEA',
                'cto' => 'CTO',
                'livret' => 'Livret',
                default => ucfirst($profile->account_type),
            };

            $walletId = DB::table('wallets')
                ->where('user_id', $profile->user_id)
                ->where('name', $walletName)
                ->value('id');

            if ($walletId) {
                DB::table('allocation_profiles')
                    ->where('id', $profile->id)
                    ->update(['wallet_id' => $walletId]);
            }
        }

        Schema::table('allocation_profiles', function (Blueprint $table) {
            $table->dropColumn('account_type');
        });
    }

    public function down(): void
    {
        Schema::table('allocation_profiles', function (Blueprint $table) {
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

            DB::table('allocation_profiles')
                ->where('wallet_id', $wallet->id)
                ->update(['account_type' => $accountType]);
        }

        Schema::table('allocation_profiles', function (Blueprint $table) {
            $table->dropForeign(['wallet_id']);
            $table->dropColumn('wallet_id');
        });
    }
};
