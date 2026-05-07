<?php

namespace App\Domains\Security\Commands;

use App\Domains\Security\Contracts\SecurityRepositoryInterface;
use App\Domains\Security\Exceptions\TickerResolutionException;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Services\YahooFinanceService;
use Illuminate\Console\Command;

class FetchSecurityPricesCommand extends Command
{
    protected $signature = 'securities:fetch-prices
        {--security= : ID d\'un titre spécifique}
        {--from= : Date de début (Y-m-d)}';

    protected $description = 'Récupère les prix de clôture depuis Yahoo Finance';

    public function handle(YahooFinanceService $service, SecurityRepositoryInterface $securityRepository): int
    {
        $securities = $this->getSecurities($securityRepository);

        if ($securities->isEmpty()) {
            $this->warn('Aucun titre à traiter.');

            return self::SUCCESS;
        }

        $useBulk = ! $this->option('from') && ! $this->option('security');

        if ($useBulk) {
            return $this->handleBulk($service, $securities);
        }

        return $this->handleSequential($service, $securities);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Security>  $securities
     */
    private function handleBulk(YahooFinanceService $service, \Illuminate\Database\Eloquent\Collection $securities): int
    {
        $this->info("Traitement en parallèle de {$securities->count()} titre(s)...");

        $totalInserted = $service->fetchAndStorePricesBulk($securities, force: true);

        $this->newLine();
        $this->info("Terminé : {$totalInserted} prix insérés/mis à jour.");

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Security>  $securities
     */
    private function handleSequential(YahooFinanceService $service, \Illuminate\Database\Eloquent\Collection $securities): int
    {
        $startDate = $this->option('from')
            ? new \DateTimeImmutable($this->option('from'))
            : null;

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
    private function getSecurities(SecurityRepositoryInterface $securityRepository): \Illuminate\Database\Eloquent\Collection
    {
        $securityId = $this->option('security');

        if ($securityId) {
            $security = $securityRepository->findById($securityId);

            return $security ? Security::query()->whereKey($security->id)->get() : Security::query()->whereRaw('0')->get();
        }

        return $securityRepository->withTransactions();
    }
}
