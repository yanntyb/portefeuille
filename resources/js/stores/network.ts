import { useOnline } from '@vueuse/core';
import { defineStore } from 'pinia';
import type { Ref } from 'vue';

/**
 * L'état du réseau, pour le seul chemin qui ne peut pas s'en passer : la saisie. Le service worker
 * met en `passthrough` toute méthode autre que GET, donc aucun filet n'existe côté worker pour un
 * POST — c'est au formulaire de refuser d'envoyer.
 *
 * Volontairement pas `useServiceWorkerStore().stale`, qui n'est pas un état de connectivité : il
 * passe à vrai quand le worker a servi du cache et ne redescend que sur un `FRESH`. On peut être en
 * ligne avec `stale` à vrai, et hors-ligne avec `stale` à faux — aucune navigation depuis la
 * coupure.
 *
 * `navigator.onLine` à vrai ne prouve pas que le serveur répond (portail captif, Herd éteint) : le
 * blocage préventif est doublé d'un `onNetworkError` à l'envoi, qui garde la saisie intacte.
 */
export const useNetworkStore = defineStore('network', () => {
    const isOnline: Ref<boolean> = useOnline();

    return { isOnline };
});
