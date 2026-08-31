<?php

namespace App\Contexts\Portfolio\Datas;

use App\Contexts\Portfolio\Enums\TransactionType;
use JsonSerializable;

/**
 * De quoi garnir le formulaire de saisie : les enveloppes de l'utilisateur, le catalogue
 * d'instruments, et les deux sens d'une opération.
 *
 * Les libellés des types viennent de l'enum et non du front : `TransactionType::getLabel()` est
 * déjà la seule définition d'« Achat » et de « Vente », et la liste des transactions les affiche
 * depuis le serveur. Deux orthographes du même mot seraient deux vérités.
 */
readonly class TransactionFormOptionsData implements JsonSerializable
{
    /**
     * @param  list<WalletOptionData>  $wallets
     * @param  list<InstrumentOptionData>  $instruments
     */
    public function __construct(
        public array $wallets,
        public array $instruments,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'wallets' => $this->wallets,
            'instruments' => $this->instruments,
            'types' => array_map(
                fn (TransactionType $type): array => [
                    'value' => $type->value,
                    'label' => $type->getLabel(),
                ],
                TransactionType::cases(),
            ),
        ];
    }
}
