import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    consumeModalEntry,
    guardModalHistory,
    pushModalEntry,
    resetModalHistory,
} from '@/lib/modalHistory';

/**
 * happy-dom n'a pas de pile d'historique navigable : `history.back()` n'émet aucun `popstate`. Les
 * deux sont donc doublés, et l'évènement est émis à la main — ce qu'on vérifie ici, c'est la
 * décision prise à sa réception, pas l'implémentation du navigateur.
 */
const pushState = vi.fn();
const back = vi.fn();

/** L'écouteur d'Inertia, posé après le nôtre : il ne doit pas être atteint quand on ravale. */
const inertia = vi.fn();

function fireBack(): void {
    window.dispatchEvent(new PopStateEvent('popstate', { state: { page: {} } }));
}

/** Les écouteurs vivent sur `window` : sans retrait, ceux d'un test intercepteraient le suivant. */
const disposers: (() => void)[] = [];

function guard(onBack: () => void): void {
    disposers.push(guardModalHistory(onBack));
    window.addEventListener('popstate', inertia);
    disposers.push((): void => window.removeEventListener('popstate', inertia));
}

afterEach((): void => {
    disposers.splice(0).forEach((dispose): void => dispose());
});

beforeEach((): void => {
    resetModalHistory();
    vi.clearAllMocks();
    vi.stubGlobal('history', { state: { page: { component: 'Dashboard' } }, pushState, back });
});

describe('entrée de garde', () => {
    it('pousse la même URL et recopie l\'état d\'Inertia', () => {
        pushModalEntry();

        /** L'adresse ne change pas : la modale n'est pas une page. */
        expect(pushState).toHaveBeenCalledWith(
            { page: { component: 'Dashboard' } },
            '',
            window.location.href,
        );
    });

    it('n\'en pousse qu\'une, quel que soit le nombre de volets traversés', () => {
        pushModalEntry();
        pushModalEntry();

        expect(pushState).toHaveBeenCalledTimes(1);
    });

    it('ne consomme rien quand aucune modale n\'est ouverte', () => {
        consumeModalEntry();

        expect(back).not.toHaveBeenCalled();
    });
});

describe('retour arrière', () => {
    it('ferme la modale et ne laisse pas Inertia restaurer la page', () => {
        const close = vi.fn();
        guard(close);

        pushModalEntry();
        fireBack();

        expect(close).toHaveBeenCalledTimes(1);
        /**
         * Inertia ferait `setQuietly(preserveState: false)`, donc remonterait la page : sections
         * repliées et props différées rechargées, juste pour fermer une modale.
         */
        expect(inertia).not.toHaveBeenCalled();
    });

    it('laisse passer un retour arrière hors modale', () => {
        const close = vi.fn();
        guard(close);

        fireBack();

        expect(close).not.toHaveBeenCalled();
        expect(inertia).toHaveBeenCalledTimes(1);
    });

    it('ne se déclenche qu\'une fois : le retour arrière suivant quitte la page', () => {
        const close = vi.fn();
        guard(close);

        pushModalEntry();
        fireBack();
        fireBack();

        expect(close).toHaveBeenCalledTimes(1);
        expect(inertia).toHaveBeenCalledTimes(1);
    });
});

describe('fermeture par un autre chemin', () => {
    it('consomme l\'entrée, et ravale le popstate qu\'elle provoque', () => {
        const close = vi.fn();
        guard(close);

        pushModalEntry();
        consumeModalEntry();
        /** Le navigateur émettrait ce `popstate` en réponse au `history.back()`. */
        fireBack();

        expect(back).toHaveBeenCalledTimes(1);
        /** Ni fermeture en double — elle a déjà eu lieu — ni restauration de page. */
        expect(close).not.toHaveBeenCalled();
        expect(inertia).not.toHaveBeenCalled();
    });

    it('rend la main à Inertia dès l\'entrée suivante', () => {
        const close = vi.fn();
        guard(close);

        pushModalEntry();
        consumeModalEntry();
        fireBack();

        /** Plus de garde en place : ce retour arrière quitte bien la page. */
        fireBack();

        expect(inertia).toHaveBeenCalledTimes(1);
        expect(close).not.toHaveBeenCalled();
    });

    it('ne laisse aucune entrée derrière après plusieurs cycles', () => {
        const close = vi.fn();
        guard(close);

        for (let cycle = 0; cycle < 3; cycle += 1) {
            pushModalEntry();
            consumeModalEntry();
            fireBack();
        }

        expect(pushState).toHaveBeenCalledTimes(3);
        expect(back).toHaveBeenCalledTimes(3);
    });
});
