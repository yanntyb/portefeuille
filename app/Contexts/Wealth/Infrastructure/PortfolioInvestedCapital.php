<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Wealth\Services\InvestedCapital;

/**
 * La photo des apports nets répartis entre toutes les classes, lue une fois par utilisateur.
 *
 * L'investi d'une exposition ne se calcule pas d'elle seule : le reliquat qu'elle libère en vendant
 * se replace dans une autre, et les liquidités portent ce que personne n'a repris
 * (`InvestedCapital::allocate()`). Chaque classe a donc besoin de la photo globale, et c'est ce
 * lecteur qui l'établit — sans lui, `PortfolioAssetClass` et `PortfolioCash` la referaient chacune
 * de leur côté, cinq fois par tableau de bord.
 *
 * Lié en `scoped` comme `GetPortfolioOverview`, dont il consomme les lectures déjà mémoïsées : le
 * portefeuille n'est ouvert qu'une fois, le découpage par exposition se fait en mémoire.
 */
class PortfolioInvestedCapital
{
    /** @var array<int, array{exposures: array<string, float>, cash: float}> */
    private array $byUser = [];

    public function __construct(
        private GetPortfolioOverview $overview,
        private InvestedCapital $capital,
    ) {}

    /** @return array{exposures: array<string, float>, cash: float} */
    public function forUser(int $userId): array
    {
        return $this->byUser[$userId] ??= $this->allocate($userId);
    }

    /** Ce qu'une exposition immobilise d'apport, nul tant qu'elle ne détient rien. */
    public function forExposure(int $userId, AssetClass $exposure): float
    {
        return $this->forUser($userId)['exposures'][$exposure->value] ?? 0.0;
    }

    /** Ce que les liquidités portent d'apport : tout ce qu'aucune exposition n'immobilise. */
    public function forCash(int $userId): float
    {
        return $this->forUser($userId)['cash'];
    }

    /** @return array{exposures: array<string, float>, cash: float} */
    private function allocate(int $userId): array
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return ['exposures' => [], 'cash' => 0.0];
        }

        $whole = ($this->overview)($user);

        $imputed = [];
        $costs = [];

        foreach (AssetClass::cases() as $exposure) {
            $scoped = ($this->overview)($user, HoldingScope::ofClasses([$exposure]));
            $imputed[$exposure->value] = $scoped->netContributions;
            $costs[$exposure->value] = $this->costOf($scoped->holdings);
        }

        return $this->capital->allocate($imputed, $costs, $whole->netContributions);
    }

    /**
     * Le coût de revient des titres détenus, lu sur les lignes et non sur `totalCost`.
     *
     * `HoldingValuator::totals()` écarte délibérément du coût toute ligne dont le gain est
     * inconnu — donc toute position dont l'actif n'a aucun cours —, pour que `totalGain` et
     * `totalCost` parlent toujours du même périmètre. Ce compromis ne vaut pas ici : « le capital
     * encore immobilisé en titres » est une question de prix de revient, pas de valorisation. Sans
     * cette lecture, un actif sans cours laisserait son apport tomber aux liquidités, qui
     * afficheraient une perte du montant de l'achat sur une caisse vide.
     *
     * @param  list<HoldingLineData>  $holdings
     */
    private function costOf(array $holdings): float
    {
        $cost = 0.0;

        foreach ($holdings as $line) {
            if ($line->avgCost !== null) {
                $cost += $line->quantity * $line->avgCost;
            }
        }

        return round($cost, 2);
    }
}
