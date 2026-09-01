<?php

namespace App\Contexts\Market\Http;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Le corps d'une création d'instrument. La validation vit dans le `Http/` du contexte propriétaire
 * de la table `assets`, comme `Portfolio\Http\TransactionRequest` pour les transactions.
 */
class InstrumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** Les pages de lecture savent s'afficher vides ; une création n'a personne pour la porter. */
        return auth()->check();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            /**
             * Requis, là où la colonne est nullable : un instrument sans ticker ne serait
             * synchronisable par aucune des trois actions, qui l'écartent toutes explicitement.
             */
            'ticker' => ['required', 'string', 'max:255'],

            'isin' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(InstrumentType::class)],

            /**
             * Envoyée et non déduite : l'utilisateur a vu la valeur sur l'écran de confirmation.
             * `AssetClass::defaultForType()` reste le défaut d'une création sans exposition, posé
             * par le hook `creating` du modèle — ce chemin-ci ne l'emprunte pas.
             */
            'assetClass' => ['required', Rule::enum(AssetClass::class)],
        ];
    }
}
