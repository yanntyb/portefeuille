import { beforeEach, describe, expect, it, vi } from 'vitest';

const { entries } = vi.hoisted(() => ({ entries: new Map<string, unknown>() }));

vi.mock('idb-keyval', () => ({
    createStore: () => 'store',
    get: async (key: string) => entries.get(key),
    set: async (key: string, value: unknown) => { entries.set(key, value); },
}));

const { readSnapshot, writeSnapshot } = await import('@/lib/snapshotStorage');

beforeEach((): void => {
    entries.clear();
});

describe('persistance de l\'instantané', () => {
    it('rend null quand rien n\'a jamais été écrit', async () => {
        expect(await readSnapshot()).toBeNull();
    });

    it('relit ce qu\'il a écrit', async () => {
        const snapshot = { version: 'abc', generatedAt: 1 } as never;

        await writeSnapshot(snapshot);

        expect(await readSnapshot()).toStrictEqual(snapshot);
    });
});
