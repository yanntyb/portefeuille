<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        DB::table('transactions')->whereNull('user_id')->update(['user_id' => $userId]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
