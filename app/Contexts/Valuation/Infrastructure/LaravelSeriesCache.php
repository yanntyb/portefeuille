<?php

namespace App\Contexts\Valuation\Infrastructure;

use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Valuation\Ports\SeriesCachePort;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Les séries de valorisation ne dépendent que des transactions de l'utilisateur et des cours
 * connus. La clé porte l'empreinte de ces deux jeux : une synchro de prix ou une transaction
 * la fait changer, et le résultat périmé n'est plus jamais lu. Aucune invalidation à écrire,
 * donc aucune à oublier — le seul risque d'une invalidation explicite serait de servir un
 * portefeuille faux.
 *
 * La durée de vie n'est qu'un filet contre l'accumulation de clés mortes.
 */
class LaravelSeriesCache implements SeriesCachePort
{
    private const TTL_SECONDS = 86400;

    /** @var array<int, string> */
    private array $stamps = [];

    public function remember(string $name, int $userId, Closure $callback): mixed
    {
        return Cache::remember(
            sprintf('valuation.%s.%d.%s', $name, $userId, $this->stampFor($userId)),
            self::TTL_SECONDS,
            $callback,
        );
    }

    /**
     * Empreinte des données dont dépend une série : dernier jour coté, et un résumé des
     * transactions de l'utilisateur. Les sommes sont dans l'empreinte parce que `updated_at`
     * ne descend pas sous la seconde : une correction saisie dans la seconde qui suit la
     * création ne se verrait pas sans elles.
     *
     * Les sommes d'`asset_id` et de `wallet_id` sont là pour la même raison : une ligne déplacée
     * d'une enveloppe ou d'un actif à l'autre ne change aucun montant, et se verrait donc passer
     * inaperçue.
     *
     * `amounts` et `autos` couvrent les mouvements d'espèces : `amount` n'entre dans aucune des
     * sommes précédentes, donc un versement ou un dividende corrigé (montant, ou passage
     * saisi/déduit) passerait inaperçu sans eux.
     *
     * `sells` compte les ventes séparément : un achat basculé en vente ne change ni le nombre de
     * lignes, ni aucune des sommes ci-dessus (même quantité, même prix, même actif), et
     * `updated_at` ne descend pas sous la seconde. Sans ce compte, la série périmée serait servie
     * jusqu'à la prochaine modification tombant dans une autre seconde.
     *
     * Volontairement lu dans les données plutôt que posé par un observateur : un import SQL ou
     * une migration contourneraient l'observateur, pas les agrégats.
     */
    private function stampFor(int $userId): string
    {
        return $this->stamps[$userId] ??= md5(implode('|', [
            (string) Price::query()->max('date'),
            (string) json_encode(Transaction::query()
                ->where('user_id', $userId)
                ->selectRaw('count(*) as total')
                ->selectRaw('max(updated_at) as touched')
                ->selectRaw('max(date) as last_date')
                ->selectRaw('coalesce(sum(quantity), 0) as quantity')
                ->selectRaw('coalesce(sum(unit_price), 0) as unit_price')
                ->selectRaw('coalesce(sum(fees), 0) as fees')
                ->selectRaw('coalesce(sum(asset_id), 0) as assets')
                ->selectRaw('coalesce(sum(wallet_id), 0) as wallets')
                ->selectRaw('coalesce(sum(amount), 0) as amounts')
                ->selectRaw('coalesce(sum(auto), 0) as autos')
                ->selectRaw("coalesce(sum(case when type = 'sell' then 1 else 0 end), 0) as sells")
                ->toBase()
                ->first()),
        ]));
    }
}
