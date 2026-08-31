import { describe, expect, it } from 'vitest';
import { isSyncing, syncTitle, type SyncState, type SyncStatus } from '@/lib/sync';

const state = (overrides: Partial<SyncState> = {}): SyncState => ({
    status: 'idle',
    startedAt: null,
    finishedAt: null,
    summary: null,
    error: null,
    ...overrides,
});

describe('isSyncing', () => {
    it('ne tient pour en cours que la mise en file et l’exécution', () => {
        const busy: SyncStatus[] = ['queued', 'running'];
        const done: SyncStatus[] = ['idle', 'succeeded', 'failed'];

        busy.forEach((status: SyncStatus): void => {
            expect(isSyncing(state({ status }))).toBe(true);
        });

        done.forEach((status: SyncStatus): void => {
            expect(isSyncing(state({ status }))).toBe(false);
        });
    });
});

describe('syncTitle', () => {
    it('annonce la synchronisation en cours, file d’attente comprise', () => {
        expect(syncTitle(state({ status: 'queued' }))).toBe('Synchronisation en cours…');
        expect(syncTitle(state({ status: 'running' }))).toBe('Synchronisation en cours…');
    });

    it('invite à relancer après un échec, sans dater la tentative ratée', () => {
        expect(syncTitle(state({ status: 'failed', finishedAt: 1_756_000_000, error: 'boom' }))).toBe(
            'Dernière synchronisation en échec — relancer',
        );
    });

    it('date la dernière réussie, en secondes reçues du serveur', () => {
        const finishedAt = Math.floor(new Date(2026, 7, 31, 14, 30).getTime() / 1000);

        expect(syncTitle(state({ status: 'succeeded', finishedAt }))).toBe(
            'Synchroniser · dernière synchro le 31/08 à 14h30',
        );
    });

    it('reste laconique quand rien n’a jamais tourné', () => {
        expect(syncTitle(state())).toBe('Synchroniser');
    });
});
