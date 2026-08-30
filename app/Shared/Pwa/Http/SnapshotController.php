<?php

namespace App\Shared\Pwa\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\MarketView\Actions\BuildMarketViewSnapshot;
use App\Contexts\RealEstate\Actions\BuildRealEstateSnapshot;
use App\Contexts\Wealth\Actions\BuildWealthSnapshot;
use Illuminate\Http\JsonResponse;

/**
 * Instantané hors-ligne : tout ce que les cinq pages affichent, en une réponse. Le service worker
 * n'indexe ses réponses que par URL, donc hors-ligne seul ce que le lecteur a déjà ouvert existe ;
 * cet instantané rend le reste lisible sans l'avoir visité.
 *
 * Ce contrôleur ne connaît aucun modèle : il n'assemble que trois actions publiques, une par
 * contexte, comme le fait `Wealth\Infrastructure` pour le tableau de bord.
 */
class SnapshotController
{
    public function __construct(
        private BuildWealthSnapshot $wealth,
        private BuildMarketViewSnapshot $marketView,
        private BuildRealEstateSnapshot $realEstate,
    ) {}

    public function __invoke(): JsonResponse
    {
        $userId = (auth()->user() ?? User::query()->first())?->id ?? 0;

        $market = ($this->marketView)($userId);

        $body = [
            'dashboard' => ($this->wealth)($userId),
            'classes' => $market['classes'],
            'assets' => $market['assets'],
            'properties' => ($this->realEstate)($userId),
        ];

        /**
         * Empreinte du contenu seul : `generatedAt` en est exclu, sinon la version changerait à
         * chaque appel et le client réécrirait son blob pour rien.
         */
        return response()->json([
            'version' => sha1((string) json_encode($body)),
            'generatedAt' => now()->timestamp,
            ...$body,
        ]);
    }
}
