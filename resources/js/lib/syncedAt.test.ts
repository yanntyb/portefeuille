import { describe, expect, it } from 'vitest';
import { displayedSyncedAt } from '@/lib/syncedAt';

/** `generatedAt` est en secondes côté serveur, `lastSyncedAt` en millisecondes côté client. */
const SECONDS = 1_700_000_000;
const MS = SECONDS * 1000;

describe('date affichée par le bandeau', () => {
    it('suit le worker tant que rien n\'est périmé', () => {
        expect(displayedSyncedAt(MS, SECONDS - 86_400, false)).toBe(MS);
    });

    it('retient la plus ancienne des deux quand l\'écran est périmé', () => {
        expect(displayedSyncedAt(MS, SECONDS - 86_400, true)).toBe(MS - 86_400_000);
    });

    it('retient le worker quand l\'instantané est plus récent', () => {
        expect(displayedSyncedAt(MS - 86_400_000, SECONDS, true)).toBe(MS - 86_400_000);
    });

    it('se contente de ce qu\'il a quand l\'autre manque', () => {
        expect(displayedSyncedAt(null, SECONDS, true)).toBe(MS);
        expect(displayedSyncedAt(MS, null, true)).toBe(MS);
        expect(displayedSyncedAt(null, null, true)).toBeNull();
    });
});
