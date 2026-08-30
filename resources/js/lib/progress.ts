import type { ProgressOptions } from '@inertiajs/core';

/**
 * Barre de chargement native d'Inertia, affichée pendant chaque visite.
 *
 * Sans délai : le préchargement au survol rend déjà la plupart des navigations instantanées, donc
 * une visite qui atteint le réseau est justement celle où le clic doit être accusé tout de suite.
 *
 * La couleur reste la valeur claire de `--brand` : Inertia ne la lit qu'au démarrage et ne suivrait
 * pas une bascule de thème à chaud. La teinte réellement affichée vient de la surcharge
 * `#nprogress .bar` dans `resources/css/app.css`, qui elle est réactive.
 */
export const progressSettings = {
    delay: 0,
    color: '#5257d6',
    includeCSS: true,
    showSpinner: false,
} satisfies ProgressOptions;
