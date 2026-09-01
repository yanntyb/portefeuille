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
     * d'espèces de l'utilisateur, toutes enveloppes confondues ; il n'est pas ventilé par
     * exposition, une somme en compte n'appartenant à aucune classe d'actif.
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
