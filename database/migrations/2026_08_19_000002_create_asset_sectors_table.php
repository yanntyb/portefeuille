<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_sectors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('sector');
            $table->decimal('weight', 8, 6);
            $table->timestamps();

            $table->unique(['asset_id', 'sector']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_sectors');
    }
};
