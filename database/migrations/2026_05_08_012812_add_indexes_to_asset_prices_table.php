<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_prices', function (Blueprint $table) {
            $table->index(['security_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('asset_prices', function (Blueprint $table) {
            $table->dropIndex(['security_id', 'date']);
        });
    }
};
