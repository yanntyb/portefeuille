<?php

namespace App\Contexts\Market\Datas;

/**
 * Rapport d'une synchronisation totale : les trois sources réunies.
 *
 * Les secteurs restent un tableau nu, comme les rend `SyncAssetSectors` : cette action ne sait pas
 * échouer partiellement — un instrument muet est simplement absent du décompte.
 */
readonly class MarketSyncReportData
{
    /** @param  array<string, int>  $sectors  nombre de secteurs écrits, indexé par ticker */
    public function __construct(
        public PriceSyncReportData $prices,
        public array $sectors,
        public DividendSyncReportData $dividends,
    ) {}

    /** Un échec total sur une source au moins : le fournisseur n'a rien rendu. */
    public function hasFailure(): bool
    {
        return $this->prices->isTotalFailure() || $this->dividends->isTotalFailure();
    }

    public function sectorsCount(): int
    {
        return count($this->sectors);
    }

    /**
     * Résumé d'une ligne, celui que le bouton du tableau de bord affiche. Compté en instruments et
     * non en lignes écrites : « 12 cours » est le nombre de titres recotés, pas de clôtures.
     *
     * « cours » est invariable, les deux autres se pluralisent comme dans les commandes par source.
     */
    public function summary(): string
    {
        $sectors = $this->sectorsCount();
        $dividends = $this->dividends->syncedCount();

        return sprintf(
            '%d cours, %d secteur%s, %d dividende%s',
            $this->prices->syncedCount(),
            $sectors, $this->plural($sectors),
            $dividends, $this->plural($dividends),
        );
    }

    private function plural(int $count): string
    {
        return $count > 1 ? 's' : '';
    }
}
