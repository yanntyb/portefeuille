<?php

namespace App\Contexts\Portfolio\Datas;

use App\Contexts\Portfolio\Enums\TransactionType;

/**
 * Une opération telle qu'elle est saisie. Ne porte que ce qu'un formulaire a le droit de dire :
 * ni `userId`, qui vient de la session, ni `realizedGain`, que l'observateur calcule, ni `id`, ni
 * `auto`, que seul le système pose.
 *
 * `assetId`, `quantity` et `unitPrice` sont nullables : un versement, un retrait ou un dividende
 * n'en portent aucun, seuls un achat ou une vente les rendent obligatoires — c'est
 * `TransactionRequest` qui l'impose selon le type.
 *
 * `Transaction` est gardé par `$guarded = ['id']`, donc tout le reste passerait en assignation de
 * masse. C'est cette Data, et non la requête, qui borne ce qui peut être écrit.
 */
readonly class TransactionInputData
{
    public function __construct(
        public int $walletId,
        public ?int $assetId,
        public string $date,
        public TransactionType $type,
        public ?float $quantity,
        public ?float $unitPrice,
        public float $fees,
        public float $amount,
    ) {}

    /**
     * Ne connaît pas HTTP : elle lit un tableau validé, que le contrôleur lui passe.
     *
     * @param  array{walletId: int|string, assetId?: int|string|null, date: string, type: string|TransactionType, quantity?: float|string|null, unitPrice?: float|string|null, fees?: float|string|null, amount?: float|string|null}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            walletId: (int) $validated['walletId'],
            assetId: isset($validated['assetId']) ? (int) $validated['assetId'] : null,
            date: $validated['date'],
            type: $validated['type'] instanceof TransactionType
                ? $validated['type']
                : TransactionType::from($validated['type']),
            quantity: isset($validated['quantity']) ? (float) $validated['quantity'] : null,
            unitPrice: isset($validated['unitPrice']) ? (float) $validated['unitPrice'] : null,
            /** Les frais sont facultatifs à la saisie : la plupart des lignes n'en portent pas. */
            fees: (float) ($validated['fees'] ?? 0),
            /** Absent pour un achat ou une vente : le montant ne vaut que pour un mouvement d'espèces. */
            amount: (float) ($validated['amount'] ?? 0),
        );
    }
}
