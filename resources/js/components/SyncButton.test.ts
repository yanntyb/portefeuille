import { describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import type { SyncState } from '@/lib/sync';

const post = vi.fn();
const start = vi.fn();
const stop = vi.fn();

/**
 * `useHttp` et `usePoll` parlent au routeur d'Inertia, absent d'un montage nu. Les doubles rendent
 * en plus le clic observable : c'est le seul effet visible d'un bouton qui n'écrit rien lui-même.
 */
vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ post, processing: false }),
    usePoll: () => ({ start, stop }),
}));

const { default: SyncButton } = await import('@/components/SyncButton.vue');

const state = (overrides: Partial<SyncState> = {}): SyncState => ({
    status: 'idle',
    startedAt: null,
    finishedAt: null,
    summary: null,
    error: null,
    ...overrides,
});

function mountButton(value: SyncState): HTMLButtonElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(SyncButton, { state: value }).mount(host);

    return host.querySelector('[data-sync-button]') as HTMLButtonElement;
}

describe('bouton de synchronisation', () => {
    it('porte son statut et reste cliquable au repos', () => {
        const button = mountButton(state());

        expect(button.getAttribute('data-sync-status')).toBe('idle');
        expect(button.disabled).toBe(false);
        expect(button.getAttribute('title')).toBe('Synchroniser');
        expect(button.querySelector('.animate-spin')).toBeNull();
    });

    it('tourne et se refuse au clic pendant une synchronisation', () => {
        const button = mountButton(state({ status: 'running', startedAt: 1_756_000_000 }));

        expect(button.disabled).toBe(true);
        expect(button.querySelector('.animate-spin')).not.toBeNull();
        expect(button.getAttribute('aria-label')).toBe('Synchronisation en cours…');
        /** Sondage démarré à l'arrivée : la synchro peut venir d'un autre onglet. */
        expect(start).toHaveBeenCalled();
    });

    it('met la synchronisation en file au clic', async () => {
        post.mockClear();

        const button = mountButton(state());
        button.click();
        await nextTick();

        expect(post).toHaveBeenCalledWith('/synchronisation', expect.anything());
    });

    it('invite à relancer après un échec', () => {
        const button = mountButton(state({ status: 'failed', error: 'boom' }));

        expect(button.disabled).toBe(false);
        expect(button.getAttribute('title')).toBe('Dernière synchronisation en échec — relancer');
    });
});
