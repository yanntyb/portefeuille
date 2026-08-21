<?php

namespace App\Contexts\RealEstate\Infrastructure;

use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use App\Contexts\RealEstate\Models\PropertyValuation;
use App\Contexts\RealEstate\Models\RentException;
use App\Contexts\RealEstate\Ports\RealEstateCachePort;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Les séries immobilières ne dépendent que des six tables du contexte et du jour courant. La clé
 * porte l'empreinte de ces sept ingrédients : un import, une migration ou une suppression en masse
 * la fait changer, et le résultat périmé n'est plus jamais lu.
 *
 * Lue dans les données et non posée par un observateur, comme `Valuation\Infrastructure\
 * LaravelSeriesCache` : `RealEstateDemoSeeder::purgeRelated()` supprime par le query builder, donc
 * aucun événement de modèle n'en part. Un observateur serait périmé dès le premier `db:seed`.
 *
 * Le jour courant fait partie de l'empreinte, contrairement à celle des titres : côté titres,
 * `Price::max('date')` avance de lui-même ; ici rien ne bouge, alors que l'escalier de valuation
 * et le capital restant dû se lisent à aujourd'hui. Sans la date, la série de lundi serait encore
 * servie vendredi.
 */
class LaravelRealEstateCache implements RealEstateCachePort
{
    private const TTL_SECONDS = 86400;

    /** @var array<int, string> */
    private array $stamps = [];

    public function remember(string $name, int $userId, Closure $callback): mixed
    {
        return Cache::remember(
            sprintf('immobilier.%s.%d.%s', $name, $userId, $this->stampFor($userId)),
            self::TTL_SECONDS,
            $callback,
        );
    }

    /**
     * Les sommes sont dans l'empreinte parce qu'`updated_at` ne descend pas sous la seconde : une
     * correction saisie dans la seconde qui suit la création ne se verrait pas sans elles.
     */
    private function stampFor(int $userId): string
    {
        return $this->stamps[$userId] ??= md5(implode('|', [
            Carbon::now()->toDateString(),
            $this->digest(Property::query()->where('user_id', $userId), [
                'coalesce(sum(acquisition_price), 0) as price',
                'coalesce(sum(acquisition_fees), 0) as fees',
                'max(acquisition_date) as last_acquisition',
            ]),
            $this->digest($this->scopedTo(PropertyValuation::query(), $userId), [
                'max(date) as last_date',
                'coalesce(sum(value), 0) as value',
            ]),
            $this->digest($this->scopedTo(Loan::query(), $userId), [
                'coalesce(sum(principal), 0) as principal',
                'coalesce(sum(annual_rate), 0) as rate',
                'coalesce(sum(term_months), 0) as term',
            ]),
            $this->digest($this->scopedTo(Lease::query(), $userId), [
                'coalesce(sum(monthly_rent), 0) as rent',
            ]),
            $this->digest($this->scopedTo(PropertyExpense::query(), $userId), [
                'coalesce(sum(amount), 0) as amount',
            ]),
            $this->digest(
                RentException::query()->whereIn(
                    'lease_id',
                    Lease::query()
                        ->whereIn('property_id', Property::query()->where('user_id', $userId)->select('id'))
                        ->select('id'),
                ),
                ['coalesce(sum(amount_override), 0) as override'],
            ),
        ]));
    }

    /**
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    private function scopedTo(Builder $query, int $userId): Builder
    {
        return $query->whereIn(
            'property_id',
            Property::query()->where('user_id', $userId)->select('id'),
        );
    }

    /**
     * Résumé d'une table : combien de lignes, quand la dernière a bougé, et les agrégats qui
     * distinguent deux états de même cardinalité.
     *
     * @param  Builder<*>  $query
     * @param  list<string>  $aggregates
     */
    private function digest(Builder $query, array $aggregates): string
    {
        $query = $query->selectRaw('count(*) as total')->selectRaw('max(updated_at) as touched');

        foreach ($aggregates as $aggregate) {
            $query = $query->selectRaw($aggregate);
        }

        return (string) json_encode($query->toBase()->first());
    }
}
