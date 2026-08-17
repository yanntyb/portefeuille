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
                ->toBase()
                ->first()),
        ]));
    }
}
