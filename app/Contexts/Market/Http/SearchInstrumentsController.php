<?php

namespace App\Contexts\Market\Http;

use App\Contexts\Market\Datas\InstrumentSearchResultData;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Ports\InstrumentProviderPort;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La recherche d'un instrument, base d'abord puis fournisseur.
 *
 * L'ordre n'est pas une optimisation : `assets.ticker` n'a pas d'index unique, et voir la ligne
 * déjà connue avant de cliquer est la seule chose qui empêche d'en créer un double. Un résultat
 * marqué d'un `existingId` mène à sa fiche, il ne se crée pas.
 *
 * JSON et non Inertia, comme `/transactions/options` : la frappe interroge cette route à chaque
 * mot, une visite Inertia rechargerait la page à chaque lettre.
 */
class SearchInstrumentsController
{
    private const LOCAL_LIMIT = 10;

    public function __construct(private InstrumentProviderPort $provider) {}

    public function __invoke(Request $request): JsonResponse
    {
        /**
         * Bornée : sans elle, un terme démesuré atteindrait quand même le `LIKE` ci-dessous et le
         * process Python du fournisseur, pour une frappe qui ne peut pas être volontaire.
         */
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $query = trim((string) $request->query('q', ''));

        /** Rien à chercher : pas de requête SQL, et surtout pas de process Python. */
        if ($query === '') {
            return response()->json([]);
        }

        /**
         * Une fabrique et non une requête déjà construite : elle sert deux fois, une fois pour
         * les lignes affichées (plafonnées) et une fois pour les tickers connus (non plafonnés).
         * Partager un seul `Builder` entre les deux y ajouterait le `limit()` de la première.
         *
         * @return EloquentBuilder<Instrument>
         */
        $matching = fn (): EloquentBuilder => Instrument::query()
            ->where(function (Builder $builder) use ($query): void {
                $builder->where('name', 'like', '%'.$query.'%')
                    ->orWhere('ticker', 'like', '%'.$query.'%');
            });

        $local = $matching()
            ->orderBy('name')
            ->limit(self::LOCAL_LIMIT)
            ->get();

        $results = $local->map(fn (Instrument $instrument): InstrumentSearchResultData => new InstrumentSearchResultData(
            symbol: $instrument->ticker ?? '',
            name: $instrument->name ?? '',
            exchange: null,
            type: $instrument->type,
            existingId: $instrument->id,
        ))->all();

        /**
         * Sur toute la correspondance, pas seulement les dix lignes affichées : un ticker connu
         * mais hors du plafond d'affichage doit quand même bloquer sa recréation par le
         * fournisseur — la recherche locale est la seule protection contre les doublons.
         */
        $known = $matching()
            ->pluck('ticker')
            ->filter()
            ->map(fn (string $ticker): string => strtolower($ticker))
            ->all();

        foreach ($this->provider->searchInstruments($query) as $hit) {
            if (in_array(strtolower($hit->symbol), $known, true)) {
                continue;
            }

            $results[] = $hit;
        }

        return response()->json(array_map(
            fn (InstrumentSearchResultData $result): array => [
                'symbol' => $result->symbol,
                'name' => $result->name,
                'exchange' => $result->exchange,
                'type' => $result->type?->value,
                'typeLabel' => $result->type?->getLabel(),
                'existingId' => $result->existingId,
            ],
            $results,
        ));
    }
}
