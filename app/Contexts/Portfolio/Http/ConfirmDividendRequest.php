<?php

namespace App\Contexts\Portfolio\Http;

use App\Contexts\Market\Enums\InstrumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Le corps de la validation d'un dividende attendu : l'enveloppe et l'actif désignent le
 * détachement, le montant est celui réellement reçu — le brut calculé, ou le net corrigé par
 * l'utilisateur.
 *
 * Sur le modèle de `TransactionRequest`, premier FormRequest de l'application : la validation vit
 * dans le `Http/` du contexte propriétaire, pas en ligne dans le contrôleur.
 */
class ConfirmDividendRequest extends FormRequest
{
    public function authorize(): bool
    {
        /**
         * Une base sans utilisateur n'a pas d'état vide en écriture : les pages de lecture savent
         * s'afficher vides, une écriture n'a personne à qui appartenir.
         */
        return auth()->check();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            /** L'enveloppe doit appartenir à l'utilisateur, comme dans `TransactionRequest`. */
            'walletId' => ['required', 'integer', Rule::exists('wallets', 'id')->where('user_id', auth()->id())],

            /** L'instrument suit le même filtre que `TransactionRequest` : le catalogue de marché. */
            'assetId' => [
                'required',
                'integer',
                Rule::exists('assets', 'id')->whereIn('type', InstrumentType::values()),
            ],

            /** Pas de date future : un détachement ne se confirme pas avant d'avoir eu lieu. */
            'exDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'],

            /** Le plafond colle à la colonne `amount` : decimal(12,2). */
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'lte:9999999999.99'],
        ];
    }
}
