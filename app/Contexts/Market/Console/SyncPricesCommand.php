<?php

namespace App\Contexts\Market\Console;

use App\Contexts\Market\Actions\SyncAssetPrices;
use App\Contexts\Market\Contracts\InstrumentRepositoryContract;
use App\Contexts\Market\Datas\PriceSyncReportData;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncPricesCommand extends Command
{
    protected $signature = 'market:sync-prices
                            {--asset= : Identifiant d\'un seul actif à synchroniser}
                            {--since= : Date de début forcée (Y-m-d), sinon reprise au dernier prix connu ; à forcer sur tout l\'historique après un split ou un dividende, que le fournisseur applique rétroactivement aux clôtures déjà stockées}';

    protected $description = 'Récupère les prix quotidiens des instruments auprès du fournisseur de marché';

    public function handle(SyncAssetPrices $syncAssetPrices, InstrumentRepositoryContract $instruments): int
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

        $report = $syncAssetPrices($asset !== null ? (int) $asset : null, $since);

        if ($report->total() === 0) {
            $this->warn('Aucun instrument à synchroniser.');

            return self::SUCCESS;
        }

        $this->printReport($report);

        return $report->isTotalFailure() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Read an option, treating an empty value as absent.
     *
     * `--asset=` means "every asset", not "the asset whose identifier is
     * nothing", and `--since=` means "resume at the last known price".
     */
    private function optionOrNull(string $name): ?string
    {
        $value = $this->option($name);

        return $value === null || $value === '' ? null : (string) $value;
    }

    private function printReport(PriceSyncReportData $report): void
    {
        foreach ($report->synced as $ticker => $count) {
            $this->line("{$ticker} : {$count} prix");
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
