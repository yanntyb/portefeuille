<?php

namespace App\Domains\Security\Commands;

use App\Domains\Security\Contracts\SecurityRepositoryInterface;
use App\Domains\Security\Exceptions\TickerResolutionException;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Services\YahooFinanceService;
use Illuminate\Console\Command;

class FetchSecuritySectorsCommand extends Command
{
    protected $signature = 'securities:fetch-sectors
        {--security= : ID d\'un titre spécifique}';

    protected $description = 'Récupère les secteurs depuis Yahoo Finance';

    public function handle(YahooFinanceService $service, SecurityRepositoryInterface $securityRepository): int
    {
        $securities = $this->getSecurities($securityRepository);

        if ($securities->isEmpty()) {
            $this->warn('Aucun titre à traiter.');

            return self::SUCCESS;
        }

        $totalInserted = 0;
        $errors = 0;

        foreach ($securities as $security) {
            $this->info("Traitement de {$security->name} ({$security->isin})...");

            try {
                $count = $service->fetchAndStoreSectors($security);
                $totalInserted += $count;
                $this->line("  → {$count} secteur(s) insérés/mis à jour");
            } catch (TickerResolutionException $e) {
                $this->error("  → {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Terminé : {$totalInserted} secteur(s) insérés/mis à jour, {$errors} erreur(s).");

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

        return $securityRepository->neededSectorUpdate();
    }
}
