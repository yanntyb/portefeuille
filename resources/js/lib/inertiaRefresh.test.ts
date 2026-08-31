import { describe, expect, it } from 'vitest';
import { refreshableKeys } from '@/lib/inertiaRefresh';

describe('refreshableKeys', () => {
    it('redemande les props chargées, y compris les synchrones', () => {
        /**
         * `overview` est le grand chiffre : synchrone, mais périmé après une écriture si le partiel
         * ne la nomme pas — une prop non demandée garde son ancienne valeur.
         */
        expect(refreshableKeys({ sync: {}, overview: {}, transactions: [] }))
            .toEqual(['sync', 'overview', 'transactions']);
    });

    it('n\'invente pas une prop différée que personne n\'a dépliée', () => {
        /** `series` absente des props : la section est repliée, rien à recalculer côté serveur. */
        expect(refreshableKeys({ overview: {} })).not.toContain('series');
    });

    it('écarte les erreurs, qu\'Inertia injecte à chaque réponse', () => {
        expect(refreshableKeys({ overview: {}, errors: {} })).toEqual(['overview']);
    });

    it('écarte les listes du formulaire, qui vivent sur leur propre route', () => {
        expect(refreshableKeys({ overview: {}, transactionOptions: {} })).toEqual(['overview']);
    });

    it('ne rend rien sur une page sans prop', () => {
        expect(refreshableKeys({})).toEqual([]);
    });
});
