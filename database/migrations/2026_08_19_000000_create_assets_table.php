<?php

use App\Contexts\Market\Enums\AssetClass;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table partagée par les instruments négociables (`Market\Models\Instrument`) et d'éventuels
     * types hors marché, discriminés par la colonne `type`.
     *
     * Le défaut d'`asset_class` n'est qu'un filet : la correspondance qui fait foi est
     * `AssetClass::defaultForType()`, appliquée par le hook `creating` du modèle.
     */
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->string('isin')->nullable();
            $table->string('ticker')->nullable();
            $table->string('name')->nullable();
            $table->string('type')->default('stock');
            $table->string('asset_class')->default(AssetClass::Equity->value);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
