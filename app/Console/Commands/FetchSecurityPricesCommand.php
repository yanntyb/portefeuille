<?php

namespace App\Console\Commands;

use App\Exceptions\TickerResolutionException;
use App\Models\Security;
use App\Services\YahooFinanceService;
use Illuminate\Console\Command;

class FetchSecurityPricesCommand extends Command
{
    protected $signature = 'securities:fetch-prices
        {--security= : ID d\'un titre spécifique}
        {--from= : Date de début (Y-m-d)}';

    protected $description = 'Récupère les prix de clôture depuis Yahoo Finance';

    public function handle(YahooFinanceService $service): int
    {
        $startDate = $this->option('from')
            ? new \DateTimeImmutable($this->option('from'))
            : null;

        $securities = $this->getSecurities();

        if ($securities->isEmpty()) {
            $this->warn('Aucun titre à traiter.');

            return self::SUCCESS;
        }

        $totalInserted = 0;
        $errors = 0;

        foreach ($securities as $security) {
            $this->info("Traitement de {$security->name} ({$security->isin})...");

            try {
                $count = $service->fetchAndStorePrices($security, $startDate);
                $totalInserted += $count;
                $this->line("  → {$count} prix insérés/mis à jour");
            } catch (TickerResolutionException $e) {
                $this->error("  → {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Terminé : {$totalInserted} prix insérés/mis à jour, {$errors} erreur(s).");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Security>
     */
    private function getSecurities(): \Illuminate\Database\Eloquent\Collection
    {
        $securityId = $this->option('security');

        if ($securityId) {
            return Security::where('id', $securityId)->get();
        }

        return Security::whereHas('transactions')->get();
    }
}
