import { frDayTime } from '@/lib/format';

export type SyncStatus = 'idle' | 'queued' | 'running' | 'succeeded' | 'failed';

/** Miroir de `Market\Datas\MarketSyncStateData` : horodatages en secondes, comme le serveur les rend. */
export interface SyncState {
    status: SyncStatus;
    startedAt: number | null;
    finishedAt: number | null;
    summary: string | null;
    error: string | null;
}

export const isSyncing = (state: SyncState): boolean => state.status === 'queued' || state.status === 'running';

/**
 * Libellé du bouton, seul texte que porte une icône : il sert de `title` et d'`aria-label`, donc il
 * dit à la fois ce que le clic fera et où en est la dernière tentative.
 *
 * `queued` et `running` se disent pareil : entre les deux il n'y a que la prise en charge par le
 * worker, ce qui ne change rien pour qui attend.
 */
export const syncTitle = (state: SyncState): string => {
    if (isSyncing(state)) {
        return 'Synchronisation en cours…';
    }

    if (state.status === 'failed') {
        return 'Dernière synchronisation en échec — relancer';
    }

    if (state.finishedAt === null) {
        return 'Synchroniser';
    }

    return `Synchroniser · dernière synchro le ${frDayTime(state.finishedAt * 1000)}`;
};
