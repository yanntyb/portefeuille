<?php

namespace App\Contexts\Portfolio\Support;

use App\Contexts\Portfolio\Models\Transaction;

/**
 * Les opérations d'un utilisateur, lues par leur propriétaire et jamais par leur seul identifiant.
 *
 * Le scope est la garde : une ligne d'un autre utilisateur est introuvable, donc les contrôleurs
 * d'écriture rendent 404 — le même code qu'un actif inconnu, et il ne révèle pas l'existence de la
 * ligne d'autrui.
 */
class UserTransactions
{
    public function find(int $userId, int $id): ?Transaction
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->whereKey($id)
            ->first();
    }
}
