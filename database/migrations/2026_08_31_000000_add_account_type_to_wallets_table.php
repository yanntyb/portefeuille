<?php

use App\Contexts\Portfolio\Enums\AccountType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'enveloppe de détention, à côté du nom du compte.
     *
     * Le défaut est le compte-titres : c'est l'enveloppe la moins affirmative — ni restriction
     * d'exposition, ni maturité —, et un compte typé à tort en PEA lèverait des alertes
     * d'éligibilité fausses là où l'inverse n'affirme rien. `BackupSeeder` corrige ensuite les
     * comptes qu'il reconnaît.
     *
     * `opened_at` reste nullable : le dump ne porte que la date d'import de la ligne, pas celle
     * d'ouverture du compte. Une ancienneté fausse serait pire qu'absente, l'affichage l'omet
     * donc tant que la colonne est nulle.
     */
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->string('account_type')->default(AccountType::Cto->value)->index();
            $table->date('opened_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropColumn(['account_type', 'opened_at']);
        });
    }
};
