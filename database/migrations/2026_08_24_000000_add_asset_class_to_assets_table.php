<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'exposition de l'actif, à côté de son enveloppe. Le défaut de colonne ne sert qu'à donner
     * une valeur aux lignes existantes le temps du rétro-remplissage qui suit : la correspondance
     * qui fait foi est `AssetClass::defaultForType()`, la même que le hook `creating` du modèle.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->string('asset_class')->default(AssetClass::Equity->value);
        });

        foreach (InstrumentType::cases() as $type) {
            DB::table('assets')
                ->where('type', $type->value)
                ->update(['asset_class' => AssetClass::defaultForType($type)->value]);
        }
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn('asset_class');
        });
    }
};
