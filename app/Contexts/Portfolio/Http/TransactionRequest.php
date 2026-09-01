<?php

namespace App\Contexts\Portfolio\Http;

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Portfolio\Actions\GetCashMovements;
use App\Contexts\Portfolio\Actions\GetPositionStock;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Services\CashLedger;
use App\Contexts\Portfolio\Services\TransactionFlow;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Le corps d'une opération saisie, pour la création comme pour la correction : les règles sont les
 * mêmes, seule l'exclusion de soi-même du stock disponible change, et elle se lit sur la route.
 *
 * Premier FormRequest de l'application : la validation vit dans le `Http/` du contexte
 * propriétaire, pas en ligne dans le contrôleur.
 *
 * Les clés sont en camelCase, comme tout le JSON déjà servi ; `TransactionInputData` les traduit
 * vers les colonnes.
 */
class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /**
         * Une base sans utilisateur n'a pas d'état vide en écriture : les pages de lecture savent
         * s'afficher vides, une opération n'a personne à qui appartenir.
         */
        return auth()->check();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /**
         * `assetId`, `quantity` et `unitPrice` n'ont de sens que pour un achat ou une vente ;
         * `amount` n'en a que pour un mouvement d'espèces. Un dividende garde un actif — il
         * expose au marché — mais ni quantité ni prix : c'est un montant reçu, pas un échange.
         */
        $isTrade = in_array($this->input('type'), [TransactionType::Buy->value, TransactionType::Sell->value], true);
        $isDividend = $this->input('type') === TransactionType::Dividend->value;

        return [
            /**
             * Pas de date future : `CalculateRealizedGain` ne retient que les achats antérieurs à
             * la vente, et l'empreinte de cache des séries prend `max(date)` — une opération datée
             * de demain ferait produire des points de valorisation au-delà d'aujourd'hui.
             */
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'],

            /**
             * L'enveloppe doit appartenir à l'utilisateur : `ProjectHolding` projette sur le couple
             * `(asset_id, wallet_id)` sans revalider, et écrirait donc dans le compte d'un autre.
             */
            'walletId' => ['required', 'integer', Rule::exists('wallets', 'id')->where('user_id', auth()->id())],

            /**
             * L'instrument n'appartient à personne — `assets` est un catalogue partagé — mais le
             * filtre sur le type réplique le scope global `market` du modèle `Instrument` : sans
             * lui, une ligne hors marché serait sélectionnable.
             */
            'assetId' => [
                $isTrade || $isDividend ? 'required' : 'prohibited',
                'integer',
                Rule::exists('assets', 'id')->whereIn('type', InstrumentType::values()),
            ],

            'type' => ['required', Rule::enum(TransactionType::class)],

            /** Les plafonds collent aux colonnes : decimal(20,8), decimal(12,4), decimal(10,2). */
            'quantity' => [$isTrade ? 'required' : 'prohibited', 'numeric', 'gt:0', 'decimal:0,8', 'lte:999999999999.99999999'],
            'unitPrice' => [$isTrade ? 'required' : 'prohibited', 'numeric', 'gt:0', 'decimal:0,4', 'lte:99999999.9999'],
            'fees' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'lte:99999999.99'],

            /** Le plafond colle à la colonne : decimal(12,2). */
            'amount' => [$isTrade ? 'prohibited' : 'required', 'numeric', 'gt:0', 'decimal:0,2', 'lte:9999999999.99'],
        ];
    }

    /** Un champ laissé vide par le formulaire vaut absent, pas chaîne vide. */
    protected function prepareForValidation(): void
    {
        if ($this->input('fees') === '') {
            $this->merge(['fees' => null]);
        }
    }

    /**
     * Vendre plus qu'on ne détient est refusé : `ProjectHolding` **supprime** la ligne de position
     * dès que la quantité tombe à zéro ou moins, si bien qu'une survente effacerait la position au
     * lieu de la mettre en défaut, et le gain réalisé se calculerait contre des achats qui ne la
     * couvrent pas.
     *
     * Volontairement aveugle aux dates, comme la projection elle-même : une vente datée avant son
     * achat passe donc, et rend la position intermédiaire négative dans les séries. Limite assumée,
     * pas un oubli.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->enum('type', TransactionType::class) !== TransactionType::Sell) {
                    return;
                }

                $held = app(GetPositionStock::class)(
                    (int) auth()->id(),
                    $this->integer('assetId'),
                    $this->integer('walletId'),
                    $this->editedTransactionId(),
                )['quantity'];

                if ((float) $this->input('quantity') > $held) {
                    $validator->errors()->add(
                        'quantity',
                        sprintf('Vous ne détenez que %s titre(s) dans cette enveloppe.', rtrim(rtrim(number_format($held, 8, ',', ' '), '0'), ',')),
                    );
                }
            },
            /**
             * On ne retire pas plus que le compte espèces ne porte : le solde d'une enveloppe n'est
             * négatif à aucune date, et un retrait à découvert ferait déduire un versement pour le
             * combler — l'application inventerait un apport que le porteur n'a pas fait.
             */
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->enum('type', TransactionType::class) !== TransactionType::Withdrawal) {
                    return;
                }

                $walletId = $this->integer('walletId');
                $date = $this->input('date');

                $balance = app(CashLedger::class)->balanceAt(
                    app(GetCashMovements::class)((int) auth()->id()),
                    $walletId,
                    $date,
                ) - $this->balanceOfEditedTransaction($walletId, $date);

                if ((float) $this->input('amount') > $balance) {
                    $validator->errors()->add(
                        'amount',
                        sprintf('Cette enveloppe ne détient que %s € en espèces.', number_format($balance, 2, ',', ' ')),
                    );
                }
            },
        ];
    }

    /**
     * La ligne en cours de correction, à retirer du stock disponible : sans quoi porter une vente
     * de 4 à 5 se comparerait à un stock dont ses propres 4 titres sont déjà déduits.
     */
    private function editedTransactionId(): ?int
    {
        $id = $this->route('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Ce que la ligne en cours de correction pesait déjà dans le solde de sa propre enveloppe, à
     * retrancher pour que porter un retrait de 300 à 500 ne se compare pas à un solde dont ses
     * propres 300 sont déjà déduits. Nul dès que la ligne n'existe pas, a changé d'enveloppe, ou
     * est postérieure à la date saisie — `CashLedger::balanceAt()` ne l'aurait alors pas comptée.
     */
    private function balanceOfEditedTransaction(int $walletId, string $date): float
    {
        $id = $this->editedTransactionId();

        if ($id === null) {
            return 0.0;
        }

        $original = Transaction::query()->where('user_id', auth()->id())->find($id);

        if ($original === null || (int) $original->wallet_id !== $walletId || $original->date->format('Y-m-d') > $date) {
            return 0.0;
        }

        return app(TransactionFlow::class)->cashDelta(
            $original->type,
            $original->quantity !== null ? (float) $original->quantity : null,
            $original->unit_price !== null ? (float) $original->unit_price : null,
            (float) $original->fees,
            $original->amount !== null ? (float) $original->amount : null,
        );
    }
}
