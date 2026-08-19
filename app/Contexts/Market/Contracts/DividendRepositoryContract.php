<?php

namespace App\Contexts\Market\Contracts;

use App\Contexts\Market\Datas\DividendData;
use App\Contexts\Market\Models\Dividend;

interface DividendRepositoryContract
{
    public function latestForAsset(int $assetId): ?Dividend;

    /**
     * Détachements de plusieurs actifs, triés par actif puis date croissante.
     *
     * Rend des tableaux et non des modèles, pour la même raison que `closesForAssetsSince()` :
     * l'appelant lit trois colonnes et n'a que faire d'une hydratation.
     *
     * @param  array<int>  $assetIds
     * @return list<array{assetId: int, exDate: string, amountPerShare: float}>
     */
    public function forAssets(array $assetIds): array;

    /**
     * Nom des instruments demandés, pour étiqueter un revenu sans exposer le modèle.
     *
     * @param  array<int>  $assetIds
     * @return array<int, string>
     */
    public function namesFor(array $assetIds): array;

    /**
     * Insère ou met à jour les détachements d'un actif.
     *
     * Appariement sur `(asset_id, ex_date)`, montant écrasé : le fournisseur révise ses valeurs.
     *
     * @param  array<int, DividendData>  $dividends
     * @return int nombre de lignes soumises à la base
     */
    public function upsertForAsset(int $assetId, array $dividends): int;
}
