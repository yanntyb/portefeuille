/**
 * Page courante d'un carrousel à défilement pagé, déduite de la position de défilement seule : le
 * carrousel ne tient aucun index en propre, c'est le navigateur qui fait foi. Un arrondi et non une
 * troncature — à mi-glissement le lecteur voit déjà surtout la page suivante, le point doit suivre.
 *
 * La largeur vaut zéro avant la première mise en page, et sur grand écran où la piste passe en
 * `display: contents` et perd sa boîte : sans le garde, la division rendrait `NaN`.
 */
export const activePageIndex = (scrollLeft: number, pageWidth: number, pageCount: number): number => {
    if (pageWidth <= 0 || pageCount <= 0) {
        return 0;
    }

    return Math.min(pageCount - 1, Math.max(0, Math.round(scrollLeft / pageWidth)));
};

/** Position de défilement d'une page, cible des sauts déclenchés depuis les points. */
export const pageScrollOffset = (index: number, pageWidth: number): number => index * pageWidth;

/**
 * Le point actif grossit et s'opacifie, les autres s'estompent. Les classes restent ici plutôt que
 * dans le gabarit : c'est le seul endroit où l'animation des points se lit et se teste.
 */
export const dotClass = (index: number, activeIndex: number): string =>
    index === activeIndex ? 'scale-125 opacity-100' : 'scale-100 opacity-40';
