import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/** Même recette que `viewport.test.ts` : le composable VueUse devient un `ref` pilotable. */
const { online } = await vi.hoisted(async () => ({ online: (await import('vue')).ref(true) }));

vi.mock('@vueuse/core', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@vueuse/core')>()),
    useOnline: () => online,
}));

const { useNetworkStore } = await import('@/stores/network');

beforeEach((): void => {
    setActivePinia(createPinia());
    online.value = true;
});

describe('store de réseau', () => {
    it('suit la connectivité du navigateur', () => {
        const network = useNetworkStore();

        expect(network.isOnline).toBe(true);

        online.value = false;

        expect(network.isOnline).toBe(false);
    });
});
