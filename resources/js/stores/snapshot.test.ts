import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Snapshot } from '@/lib/snapshotContract';

const { stored, writes } = vi.hoisted(() => ({
    stored: { value: null as Snapshot | null },
    writes: [] as Snapshot[],
}));

vi.mock('@/lib/snapshotStorage', () => ({
    readSnapshot: async () => stored.value,
    writeSnapshot: async (snapshot: Snapshot) => { writes.push(snapshot); },
}));

const { useSnapshotStore } = await import('@/stores/snapshot');

const build = (version: string): Snapshot => ({
    version,
    generatedAt: 1_700_000_000,
    dashboard: { overview: {}, series: {}, income: {} },
    instruments: { list: {}, byId: { '7': { instrument: { name: 'Air Liquide' } } } },
    crypto: { list: {}, byId: {} },
    properties: { list: {}, byId: { '3': { property: { name: 'T2 Lyon 7e' } } } },
} as unknown as Snapshot);

beforeEach((): void => {
    setActivePinia(createPinia());
    stored.value = null;
    writes.length = 0;
    vi.unstubAllGlobals();
});

describe('hydratation', () => {
    it('charge ce qu\'IndexedDB avait retenu', async () => {
        stored.value = build('abc');

        const store = useSnapshotStore();
        await store.hydrate();

        expect(store.snapshot?.version).toBe('abc');
    });

    it('reste vide quand rien n\'a été retenu', async () => {
        const store = useSnapshotStore();
        await store.hydrate();

        expect(store.snapshot).toBeNull();
    });
});

describe('resynchronisation', () => {
    it('remplace l\'instantané et l\'écrit quand la version change', async () => {
        stored.value = build('abc');
        vi.stubGlobal('fetch', vi.fn(async () => new Response(JSON.stringify(build('def')), {
            headers: { 'Content-Type': 'application/json' },
        })));

        const store = useSnapshotStore();
        await store.hydrate();
        await store.sync();

        expect(store.snapshot?.version).toBe('def');
        expect(writes).toHaveLength(1);
    });

    it('n\'écrit rien quand la version est inchangée', async () => {
        stored.value = build('abc');
        vi.stubGlobal('fetch', vi.fn(async () => new Response(JSON.stringify(build('abc')))));

        const store = useSnapshotStore();
        await store.hydrate();
        await store.sync();

        expect(writes).toHaveLength(0);
    });

    it('garde silencieusement ce qu\'il avait quand le réseau échoue', async () => {
        stored.value = build('abc');
        vi.stubGlobal('fetch', vi.fn(async () => { throw new TypeError('offline'); }));

        const store = useSnapshotStore();
        await store.hydrate();
        await store.sync();

        expect(store.snapshot?.version).toBe('abc');
        expect(store.syncing).toBe(false);
    });
});

describe('sélecteurs par entité', () => {
    it('retrouve une fiche jamais visitée par son identifiant', async () => {
        stored.value = build('abc');

        const store = useSnapshotStore();
        await store.hydrate();

        expect(store.instrumentPage('7')?.instrument.name).toBe('Air Liquide');
        expect(store.propertyPage('3')?.property.name).toBe('T2 Lyon 7e');
        expect(store.instrumentPage('999')).toBeNull();
    });
});
