<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rattache les transactions existantes à un utilisateur. Le compte de repli n'est créé que s'il
     * y a réellement des lignes orphelines : une base fraîche n'a rien à rattacher, et n'a donc pas
     * à hériter d'un utilisateur.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        if (! DB::table('transactions')->whereNull('user_id')->exists()) {
            return;
        }

        $userId = DB::table('users')->where('email', 'yanntyb.lbc@gmail.com')->value('id');

        if (! $userId) {
            $userId = DB::table('users')->insertGetId([
                'name' => 'Yann',
                'email' => 'yanntyb.lbc@gmail.com',
                'password' => Hash::make('pass'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('transactions')->whereNull('user_id')->update(['user_id' => $userId]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
