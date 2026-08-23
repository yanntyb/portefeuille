import { computed, type ComputedRef } from 'vue';

/**
 * Rend la dernière valeur connue en attendant celle du réseau. La prop Inertia gagne dès qu'elle
 * arrive — l'instantané ne comble que l'intervalle, y compris l'intervalle infini d'une page
 * ouverte hors-ligne sans jamais avoir été visitée.
 *
 * `null` signifie « ni l'un ni l'autre » : à la section d'afficher son squelette, puis son message
 * d'indisponibilité.
 */
export function aheadOfNetwork<T>(
    prop: () => T | undefined,
    stored: () => T | null | undefined,
): ComputedRef<T | null> {
    return computed((): T | null => prop() ?? stored() ?? null);
}
