/**
 * Plus grande valeur d'un lot, plancher à zéro : un `Math.max` sur un tableau vide rendrait
 * `-Infinity`, et une barre ne se mesure pas contre une valeur négative.
 */
export const largestOf = (values: number[]): number => Math.max(0, ...values);

/**
 * Largeur d'une barre, en pourcentage CSS, mesurée contre la plus grande valeur du lot et non
 * contre leur total : sans ça la plus petite part devient invisible. L'appelant passe des valeurs
 * absolues quand son lot peut être signé.
 */
export const relativeBarWidth = (value: number, largest: number): string =>
    largest > 0 ? `${(value / largest) * 100}%` : '0%';
