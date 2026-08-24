import { computed, type ComputedRef } from 'vue';

/**
 * Rend la dernière valeur connue en attendant celle du réseau. La prop Inertia gagne dès qu'elle
 * arrive — l'instantané ne comble que l'intervalle, y compris l'intervalle infini d'une page
 * ouverte hors-ligne sans jamais avoir été visitée.
 *
 * L'instantané rendu est verrouillé sur sa première valeur non nulle : la resynchronisation de
 * fond remplace le blob en mémoire pendant que la page est déjà peinte, et sans ce verrou un
 * graphe déjà tracé se reconstruisait sur une autre échelle avant même que le réseau ait répondu.
 * Le blob frais n'est donc lu qu'au prochain rendu de la page.
 *
 * `null` signifie « ni l'un ni l'autre » : à la section d'afficher son squelette, puis son message
 * d'indisponibilité.
 */
export function aheadOfNetwork<T>(
    prop: () => T | undefined,
    stored: () => T | null | undefined,
): ComputedRef<T | null> {
    let latched: T | null = null;

    return computed((): T | null => {
        const fromNetwork = prop();

        if (fromNetwork !== undefined) {
            return fromNetwork;
        }

        /** `??=` relit tant que rien n'a été retenu : un instantané pas encore hydraté ne verrouille rien. */
        latched ??= stored() ?? null;

        return latched;
    });
}
