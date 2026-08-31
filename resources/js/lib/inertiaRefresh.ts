/**
 * Ce qu'une écriture doit faire redemander à la page.
 *
 * Les props que le serveur ne renomme pas dans un partiel **gardent leur ancienne valeur** : après
 * un achat, `overview` — le grand chiffre, pourtant synchrone — resterait faux si on ne la
 * demandait pas. Il faut donc bel et bien nommer les props déjà chargées.
 *
 * Mais surtout : ne pas nommer celles qui ne le sont pas. Une prop différée dont la section est
 * repliée n'est pas dans `page.props` ; la lister ferait calculer au serveur un historique que
 * personne ne regarde. Les clés présentes **sont** exactement les sections que le lecteur a
 * dépliées, ce qui rend la liste juste sans qu'aucune page n'ait de tableau à tenir à jour.
 */
const NEVER_REFRESHED = [
    /** Injectée par Inertia à chaque réponse, ce n'est pas une prop de page. */
    'errors',
    /** Les listes du formulaire vivent sur leur propre route, hors du cycle des props. */
    'transactionOptions',
];

export const refreshableKeys = (props: Record<string, unknown>): string[] =>
    Object.keys(props).filter((key: string): boolean => !NEVER_REFRESHED.includes(key));
