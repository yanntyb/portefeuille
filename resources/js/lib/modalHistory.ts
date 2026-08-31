/**
 * Le retour arrière ferme la modale au lieu de quitter la page.
 *
 * Une entrée d'historique est poussée à l'ouverture, sur la **même URL** et avec l'état d'Inertia
 * recopié : l'adresse ne bouge pas, et l'entrée est indiscernable d'une entrée d'Inertia pour qui
 * la lirait. Le retour arrière la consomme, la modale se ferme, et personne d'autre n'est prévenu.
 *
 * Pourquoi ne pas laisser Inertia traiter ce `popstate` : sur un état valide, il fait
 * `setQuietly(data, { preserveState: false })`, et l'adaptateur Vue régénère alors sa `key`. La page
 * se remonterait — toutes les sections repliées, les props différées rechargées depuis
 * l'historique — soit exactement ce que `preserveState` évite au moment de la saisie.
 *
 * D'où `stopImmediatePropagation()`, qui n'a d'effet que si notre écouteur passe le premier : sur
 * `window`, les écouteurs d'un même évènement se déclenchent dans l'ordre d'enregistrement, sans
 * égard pour la phase. `guardModalHistory()` doit donc être appelé AVANT `createInertiaApp`.
 */

/** Une entrée de garde est en place : le prochain retour arrière nous appartient. */
let guarded = false;

/**
 * Un `popstate` provoqué par nous, à ravaler. Fermer la modale autrement que par le retour arrière
 * doit consommer l'entrée de garde, sans quoi le retour arrière suivant semblerait ne rien faire
 * tout en remontant la page, et il faudrait appuyer deux fois pour quitter.
 */
let swallowNext = false;

/** Rend de quoi se retirer : l'application n'en a pas l'usage, les tests si. */
export function guardModalHistory(onBack: () => void): () => void {
    const listener = (event: PopStateEvent): void => {
        if (swallowNext) {
            swallowNext = false;
            guarded = false;
            event.stopImmediatePropagation();

            return;
        }

        if (!guarded) {
            return;
        }

        /** Avant l'appel : `onBack` ferme la modale, et la fermeture ne doit rien reconsommer. */
        guarded = false;
        event.stopImmediatePropagation();
        onBack();
    };

    window.addEventListener('popstate', listener);

    return (): void => window.removeEventListener('popstate', listener);
}

export function pushModalEntry(): void {
    if (guarded) {
        return;
    }

    guarded = true;

    /** L'état d'Inertia est recopié tel quel, et l'URL reste la même : rien ne change à l'écran. */
    window.history.pushState(window.history.state, '', window.location.href);
}

export function consumeModalEntry(): void {
    if (!guarded) {
        return;
    }

    swallowNext = true;
    window.history.back();
}

/** Remet le module à neuf entre deux tests ; l'application n'en a pas l'usage. */
export function resetModalHistory(): void {
    guarded = false;
    swallowNext = false;
}
