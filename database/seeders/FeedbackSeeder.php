<?php

namespace Database\Seeders;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FeedbackSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var list<array{subject: string, body: string}> */
    private const FEEDBACKS = [
        [
            'subject' => 'Ajouter un graphique camembert',
            'body' => 'Ce serait pratique d\'avoir un camembert pour visualiser la répartition du portefeuille par secteur.',
        ],
        [
            'subject' => 'Export CSV des transactions',
            'body' => 'J\'aimerais pouvoir exporter mes transactions au format CSV pour les importer dans un tableur.',
        ],
        [
            'subject' => 'Mode sombre automatique',
            'body' => 'Le mode sombre est top, mais ce serait bien qu\'il suive le thème système automatiquement.',
        ],
        [
            'subject' => 'Notifications de dividendes',
            'body' => 'Recevoir une notification quand un dividende est versé sur un titre en portefeuille serait très utile.',
        ],
        [
            'subject' => 'Comparaison avec un benchmark',
            'body' => 'Pouvoir comparer la performance de mon portefeuille avec un indice de référence comme le MSCI World.',
        ],
    ];

    public function run(User $user): void
    {
        foreach (self::FEEDBACKS as $feedback) {
            Feedback::create([
                'user_id' => $user->id,
                ...$feedback,
            ]);
        }
    }
}
