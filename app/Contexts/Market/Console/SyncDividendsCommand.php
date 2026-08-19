<?php

namespace App\Contexts\Market\Console;

use App\Contexts\Market\Actions\SyncAssetDividends;
use App\Contexts\Market\Contracts\InstrumentRepositoryContract;
use App\Contexts\Market\Datas\DividendSyncReportData;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncDividendsCommand extends Command
{
    protected $signature = 'market:sync-dividends
                            {--asset= : Identifiant d\'un seul actif à synchroniser}
                            {--since= : Date de début forcée (Y-m-d), sinon reprise au dernier détachement connu}';

    protected $description = 'Récupère les détachements de dividende des instruments auprès du fournisseur de marché';

    public function handle(SyncAssetDividends $syncAssetDividends, InstrumentRepositoryContract $instruments): int
    {
        $since = $this->optionOrNull('since');

        if ($since !== null && ! Carbon::hasFormat($since, 'Y-m-d')) {
            $this->error("Date de début invalide : « {$since} ». Format attendu : AAAA-MM-JJ.");

            return self::FAILURE;
        }

        $asset = $this->optionOrNull('asset');

        if ($asset !== null && $instruments->findById((int) $asset) === null) {
            $this->error("Aucun instrument ne porte l'identifiant « {$asset} ».");

            return self::FAILURE;
        }

        $report = $syncAssetDividends($asset !== null ? (int) $asset : null, $since);

        if ($report->total() === 0) {
            $this->warn('Aucun instrument à synchroniser.');

            return self::SUCCESS;
        }

        $this->printReport($report);

        return $report->isTotalFailure() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Lit une option en traitant une valeur vide comme absente.
     *
     * `--asset=` veut dire « tous les actifs », et `--since=` « reprendre au dernier
     * détachement connu ».
     */
    private function optionOrNull(string $name): ?string
    {
        $value = $this->option($name);

        return $value === null || $value === '' ? null : (string) $value;
    }

    private function printReport(DividendSyncReportData $report): void
    {
        foreach ($report->synced as $ticker => $count) {
            $this->line("{$ticker} : {$count} détachement".$this->plural($count));
        }

        foreach ($report->failed as $ticker) {
            $this->error("{$ticker} : échec");
        }

        if ($report->error !== null) {
            $this->error("Échec de la récupération auprès du fournisseur : {$report->error}");
        }

        $this->newLine();
        $this->line($this->summary($report->total(), $report->syncedCount(), count($report->failed)));
    }

    private function summary(int $total, int $synced, int $failed): string
    {
        return sprintf(
            '%d instrument%s, %d synchronisé%s, %d échec%s',
            $total, $this->plural($total),
            $synced, $this->plural($synced),
            $failed, $this->plural($failed),
        );
    }

    private function plural(int $count): string
    {
        return $count > 1 ? 's' : '';
    }
}
