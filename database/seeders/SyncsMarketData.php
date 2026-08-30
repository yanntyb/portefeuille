<?php

namespace Database\Seeders;

use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Models\Price;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

trait SyncsMarketData
{
    /**
     * Remplit les prix et les secteurs d'un actif avec les commandes de synchronisation, puis
     * relit l'historique persisté.
     *
     * Passer par les commandes plutôt que par le fournisseur évite au seeder de dupliquer la
     * fenêtre de récupération et le format d'écriture des dates, et fait des données de démo
     * le résultat du même chemin qu'en production.
     *
     * @return Collection<int, array{date: string, close: float}>
     */
    private function syncMarketData(int $assetId, string $since): Collection
    {
        Artisan::call('market:sync-prices', ['--asset' => $assetId, '--since' => $since]);
        Artisan::call('market:sync-sectors', ['--asset' => $assetId]);

        return app(PriceRepositoryContract::class)
            ->forAssetSince($assetId, Carbon::parse($since))
            ->map(fn (Price $price): array => [
                'date' => $price->date->format('Y-m-d'),
                'close' => (float) $price->close,
            ])
            ->filter(fn (array $row): bool => $row['close'] > 0)
            ->values();
    }

    /**
     * Remplit les prix, les secteurs et les détachements de tous les actifs en une passe.
     *
     * Le fournisseur récupère les cours et les dividendes par lot : une commande sans `--asset`
     * coûte un seul appel là où une boucle par actif en coûterait autant que d'instruments.
     *
     * Les détachements partent de la même borne basse que les cours : sans eux, une base
     * fraîchement seedée n'afficherait aucun dividende perçu tant que la tâche planifiée
     * `market:sync-dividends` ne serait pas passée.
     */
    private function syncAllMarketData(string $since): void
    {
        Artisan::call('market:sync-prices', ['--since' => $since]);
        Artisan::call('market:sync-sectors');
        Artisan::call('market:sync-dividends', ['--since' => $since]);
    }
}
