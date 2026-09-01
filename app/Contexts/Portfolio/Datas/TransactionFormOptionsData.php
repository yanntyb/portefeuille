<?php

namespace App\Contexts\Portfolio\Datas;

use App\Contexts\Portfolio\Enums\TransactionType;
use JsonSerializable;

/**
 * De quoi garnir le formulaire de saisie : les enveloppes de l'utilisateur, le catalogue
 * d'instruments, les positions détenues et les deux sens d'une opération.
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
     * @param  list<HeldStockData>  $held
     */
    public function __construct(
        public array $wallets,
        public array $instruments,
        public array $held,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'wallets' => $this->wallets,
            'instruments' => $this->instruments,
            'held' => $this->held,
            /**
             * `Dividend` en est absent : un dividende ne se saisit qu'en validant son détachement,
             * seul chemin qui connaisse l'ex-date, l'enveloppe détentrice et le garde anti-doublon.
             * `TransactionRequest` le refuse aussi côté serveur — cette liste ne fait que ne pas le
             * proposer.
             */
            'types' => array_map(
                fn (TransactionType $type): array => [
                    'value' => $type->value,
                    'label' => $type->getLabel(),
                ],
                array_values(array_filter(
                    TransactionType::cases(),
                    fn (TransactionType $type): bool => $type !== TransactionType::Dividend,
                )),
            ),
        ];
    }
}
