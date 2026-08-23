/**
 * Date que le bandeau annonce. Quand l'écran est périmé, il mélange deux âges : les props servies
 * par le cache du worker, et les valeurs venues de l'instantané. Annoncer la plus récente des deux
 * ferait lire « synchronisé il y a 2 minutes » devant des chiffres vieux d'une journée de marché —
 * on annonce donc la plus ancienne, la seule qui ne mente sur rien.
 *
 * `generatedAt` arrive du serveur en secondes, `lastSyncedAt` est un `Date.now()` en millisecondes.
 */
export function displayedSyncedAt(
    lastSyncedAt: number | null,
    generatedAt: number | null,
    stale: boolean,
): number | null {
    const snapshotAt = generatedAt === null ? null : generatedAt * 1000;

    if (!stale) {
        return lastSyncedAt;
    }

    if (lastSyncedAt === null) {
        return snapshotAt;
    }

    if (snapshotAt === null) {
        return lastSyncedAt;
    }

    return Math.min(lastSyncedAt, snapshotAt);
}
