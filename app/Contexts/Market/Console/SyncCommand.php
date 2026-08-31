<?php

namespace App\Contexts\Market\Console;

use App\Contexts\Market\Actions\SyncMarketData;
use App\Contexts\Market\Datas\MarketSyncReportData;
use Illuminate\Console\Command;

/**
 * Synchronisation totale en terminal. Les trois commandes par source restent : elles gardent leurs
 * options (`--asset`, `--since`) et leurs horaires au planificateur ; celle-ci n'existe que pour
 * tout lancer d'un coup, comme le fait le bouton du tableau de bord.
 */
class SyncCommand extends Command
{
    protected $signature = 'market:sync';

    protected $description = 'Synchronise cours, secteurs et dividendes auprès du fournisseur de marché';

    public function handle(SyncMarketData $syncMarketData): int
    {
        $report = $syncMarketData();

        $this->printReport($report);

        return $report->hasFailure() ? self::FAILURE : self::SUCCESS;
    }

    private function printReport(MarketSyncReportData $report): void
    {
        foreach ($report->prices->failed as $ticker) {
            $this->error("{$ticker} : échec des cours");
        }

        foreach ($report->dividends->failed as $ticker) {
            $this->error("{$ticker} : échec des dividendes");
        }

        if ($report->prices->error !== null) {
            $this->error("Échec de la récupération des cours : {$report->prices->error}");
        }

        if ($report->dividends->error !== null) {
            $this->error("Échec de la récupération des dividendes : {$report->dividends->error}");
        }

        $this->newLine();
        $this->line($report->summary());
    }
}
