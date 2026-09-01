<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

readonly class PortfolioOverviewData implements JsonSerializable
{
    /**
     * `$totalGain` est le gain latent des lignes encore détenues ; `$totalRealizedGain` celui déjà
     * encaissé sur les ventes, y compris sur des actifs entièrement soldés qui n'ont plus de ligne.
     * `$totalCost` reste le coût de revient des titres détenus — il sert encore au calcul du gain
     * latent et de son pourcentage à la ligne.
     *
     * `$netContributions` porte un tout autre sens : ce que le porteur a réellement sorti de sa
     * poche (`CashLedger::netContributions()`), et non le coût des titres. Racheter avec le produit
     * d'une vente ne crée aucun apport nouveau, quand `$totalCost` l'aurait compté une seconde fois
     * — c'est la mesure destinée à remplacer « Investi » côté écran. `$cash` est le solde
     * d'espèces de l'utilisateur, toutes enveloppes confondues, ou — quand une exposition est
     * demandée — le seul cash **d'origine** de celle-ci : le produit de ses ventes et de ses
     * dividendes pas encore replacé, que le FIFO d'imputation de `CashLedger` sait dire. Le solde
     * n'appartient à aucune classe d'actif, mais son origine, elle, se lit.
     *
     * @param  list<HoldingLineData>  $holdings
     */
    public function __construct(
        public float $totalValue,
        public float $totalCost,
        public float $totalGain,
        public ?float $totalGainPct,
        public float $totalRealizedGain,
        public float $netContributions,
        public float $cash,
        public array $holdings,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, 0.0, 0.0, null, 0.0, 0.0, 0.0, []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'totalValue' => $this->totalValue,
            'totalCost' => $this->totalCost,
            'totalGain' => $this->totalGain,
            'totalGainPct' => $this->totalGainPct,
            'totalRealizedGain' => $this->totalRealizedGain,
            'netContributions' => $this->netContributions,
            'cash' => $this->cash,
            'holdings' => array_map(fn (HoldingLineData $h) => $h->jsonSerialize(), $this->holdings),
        ];
    }
}
