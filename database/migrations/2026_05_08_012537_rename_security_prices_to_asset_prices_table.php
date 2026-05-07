<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('security_prices', 'asset_prices');
    }

    public function down(): void
    {
        Schema::rename('asset_prices', 'security_prices');
    }
};
