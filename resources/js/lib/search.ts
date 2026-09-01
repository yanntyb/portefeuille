/**
 * Déplie un intitulé pour le comparer : accents retirés, casse ramenée au bas. `NFD` sépare la
 * lettre de son signe, la classe `\p{Diacritic}` enlève le signe.
 *
 * Partagé par le combobox du formulaire et le catalogue d'une exposition : deux copies de cette
 * normalisation finiraient par diverger, et « société » cesserait de trouver « Societe » d'un côté.
 */
export const fold = (text: string): string =>
    text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
