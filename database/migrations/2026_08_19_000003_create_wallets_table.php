<?php

use App\Contexts\Portfolio\Enums\AccountType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le défaut d'`account_type` est le compte-titres : l'enveloppe la moins affirmative — ni
     * restriction d'exposition, ni maturité —, et un compte typé à tort en PEA lèverait des
     * alertes d'éligibilité fausses là où l'inverse n'affirme rien. `BackupSeeder` corrige
     * ensuite les comptes qu'il reconnaît.
     *
     * `opened_at` est nullable : le dump ne porte que la date d'import de la ligne, pas celle
     * d'ouverture du compte. Une ancienneté fausse serait pire qu'absente, l'affichage l'omet
     * donc tant que la colonne est nulle.
     *
     * `broker` est l'établissement qui tient le compte, et reste nullable : un portefeuille saisi
     * à la main n'a pas à le nommer, l'affichage retombe alors sur le nom du compte. Il vit ici et
     * non sur la transaction : un compte n'a qu'un établissement, et le garde même sans aucun
     * mouvement.
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('broker')->nullable();
            $table->string('account_type')->default(AccountType::Cto->value)->index();
            $table->date('opened_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
