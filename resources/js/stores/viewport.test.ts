import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/** happy-dom fige `matchMedia` : on le remplace par un `ref` pilotable, comme dans theme.test.ts. */
const { wide } = await vi.hoisted(async () => ({ wide: (await import('vue')).ref(false) }));

vi.mock('@vueuse/core', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@vueuse/core')>()),
    useMediaQuery: () => wide,
}));

const { useViewportStore } = await import('@/stores/viewport');

beforeEach((): void => {
    setActivePinia(createPinia());
    wide.value = false;
});

describe('store de viewport', () => {
    it('suit la requête média', () => {
        const viewport = useViewportStore();

        expect(viewport.isWide).toBe(false);

        wide.value = true;

        expect(viewport.isWide).toBe(true);
    });
});
