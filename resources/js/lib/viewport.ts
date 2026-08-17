import { useMediaQuery } from '@vueuse/core';
import type { Ref } from 'vue';

/**
 * Seuil `md` de Tailwind, en rem pour suivre la taille de police du lecteur comme le font les
 * variantes CSS. Partagé plutôt que recopié : un écart entre ce seuil et celui des classes
 * `md:` ferait cohabiter deux comportements sur la même largeur d'écran.
 */
const WIDE_VIEWPORT = '(min-width: 48rem)';

/**
 * Vrai au-delà du mobile. Réservé aux composants qui changent de *comportement* et pas seulement
 * d'apparence — le reste passe par les variantes `md:`, qui n'ont besoin d'aucun JavaScript.
 *
 * `ref` de module, comme `isDark` : la requête média est unique pour toute l'application.
 */
export const isWideViewport: Ref<boolean> = useMediaQuery(WIDE_VIEWPORT);
