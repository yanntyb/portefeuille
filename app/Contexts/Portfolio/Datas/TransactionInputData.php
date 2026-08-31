<?php

namespace App\Contexts\Portfolio\Datas;

use App\Contexts\Portfolio\Enums\TransactionType;

/**
 * Une opération telle qu'elle est saisie. Ne porte que ce qu'un formulaire a le droit de dire :
 * ni `userId`, qui vient de la session, ni `realizedGain`, que l'observateur calcule, ni `id`.
 *
 * `Transaction` est gardé par `$guarded = ['id']`, donc tout le reste passerait en assignation de
 * masse. C'est cette Data, et non la requête, qui borne ce qui peut être écrit.
 */
readonly class TransactionInputData
{
    public function __construct(
        public int $walletId,
        public int $assetId,
        public string $date,
        public TransactionType $type,
        public float $quantity,
        public float $unitPrice,
        public float $fees,
    ) {}

    /**
     * Ne connaît pas HTTP : elle lit un tableau validé, que le contrôleur lui passe.
     *
     * @param  array{walletId: int|string, assetId: int|string, date: string, type: string|TransactionType, quantity: float|string, unitPrice: float|string, fees?: float|string|null}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            walletId: (int) $validated['walletId'],
            assetId: (int) $validated['assetId'],
            date: $validated['date'],
            type: $validated['type'] instanceof TransactionType
                ? $validated['type']
                : TransactionType::from($validated['type']),
            quantity: (float) $validated['quantity'],
            unitPrice: (float) $validated['unitPrice'],
            /** Les frais sont facultatifs à la saisie : la plupart des lignes n'en portent pas. */
            fees: (float) ($validated['fees'] ?? 0),
        );
    }
}
