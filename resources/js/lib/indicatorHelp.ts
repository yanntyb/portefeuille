import type { IndicatorId } from '@/lib/instrumentAnalysis';

export interface IndicatorHelp {
    title: string;
    subtitle: string;
    body: string[];
    /** Encart en chasse fixe, comme dans l'aide des performances. */
    formula?: string;
    /** La limite de lecture, posée en dernier et en retrait : ce que le chiffre ne dit pas. */
    caveat?: string;
}

/**
 * Le texte des aides de la section Analyse, en données plutôt qu'en gabarit : sept indicateurs
 * feraient d'un composant à `v-if` un mur illisible, et un test peut vérifier ici qu'aucun bouton
 * n'ouvre du vide.
 */
export const indicatorHelp: Record<IndicatorId, IndicatorHelp> = {
    pru: {
        title: 'Comment lire le PRU',
        subtitle: 'Prix de revient unitaire',
        body: [
            'C\'est le prix moyen que tu as payé par part, tous tes achats confondus : le total sorti de ton compte divisé par le nombre de parts détenues.',
            'Il sert de référence à tout le reste de la fiche — le gain latent, l\'écart au cours — mais ce n\'est pas un signal : le marché ne sait pas à quel prix tu es entré.',
        ],
        formula: 'somme des achats (frais compris) ÷ quantité détenue',
    },
    pruGap: {
        title: 'Comment lire l\'écart au PRU',
        subtitle: 'La distance entre le cours du jour et ton prix de revient',
        body: [
            'La part de gain ou de perte latente sur chaque part détenue : où en est ta position par rapport à ce qu\'elle t\'a coûté.',
        ],
        formula: '(cours − PRU) ÷ PRU',
        caveat: 'Ne dit rien du bon moment pour agir : une ligne à +80 % peut rester une bonne affaire, une ligne à −20 % un piège. Le marché ignore ton prix d\'entrée.',
    },
    high52w: {
        title: 'Comment lire le plus-haut 52 semaines',
        subtitle: 'Le sommet de l\'année boursière',
        body: [
            'La plus haute clôture des 252 dernières séances, soit environ un an de cotation. Un repère pour situer le prix du jour dans son propre historique récent.',
        ],
    },
    high52wGap: {
        title: 'Comment lire la distance au plus-haut',
        subtitle: 'De combien le cours a reculé depuis son sommet annuel',
        body: [
            'Zéro veut dire que le titre est sur son plus-haut de l\'année. −22 % qu\'il a rendu près d\'un quart depuis ce sommet.',
            'C\'est la mesure de repli la plus directe : elle dit ce qu\'un renfort achète en escompte par rapport au meilleur prix récent.',
        ],
        caveat: 'Un fort recul depuis le sommet n\'est une occasion que si la raison du recul est passagère. L\'indicateur ne la connaît pas.',
    },
    atrPct: {
        title: 'Comment lire l\'ATR',
        subtitle: 'L\'amplitude quotidienne moyenne, en pourcentage du cours',
        body: [
            'De combien bouge une journée ordinaire. « 1,9 % » veut dire que l\'écart entre le haut et le bas d\'une séance vaut en moyenne 1,9 % du cours.',
            'Exprimé en pourcentage, il se compare d\'un instrument à l\'autre : c\'est le chiffre qui dit lequel de deux titres secoue le plus.',
            'Il sert à dimensionner : à risque égal, plus l\'ATR est haut, plus la ligne doit rester petite.',
        ],
        formula: 'moyenne des amplitudes vraies sur 14 séances ÷ dernier cours',
    },
    maxDrawdown: {
        title: 'Comment lire le max drawdown',
        subtitle: 'La pire chute depuis un sommet',
        body: [
            'La plus forte baisse subie entre un plus-haut et le creux qui l\'a suivi, sur l\'historique connu, cinq ans au plus. Le risque déjà vécu par le titre, en une mesure.',
            'Il répond à « qu\'est-ce que j\'aurais encaissé au pire moment », ce qu\'aucune moyenne de volatilité ne dit aussi clairement.',
        ],
        caveat: 'C\'est du passé, pas une borne : rien n\'empêche une chute plus profonde que celles déjà vues.',
    },
    portfolioWeight: {
        title: 'Comment lire le poids du portefeuille',
        subtitle: 'La part de cette ligne dans ta valeur totale',
        body: [
            'La valeur de marché de cette position rapportée à celle de tout le portefeuille, immobilier exclu.',
            'C\'est le chiffre qui répond vraiment à « je renforce de combien » : une ligne qui pèse déjà 15 % ne se renforce pas comme une ligne à 2 %.',
        ],
        formula: 'valeur de la position ÷ valeur de toutes les positions',
    },
};
