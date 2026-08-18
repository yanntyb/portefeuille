import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    applyUpdate,
    canInstall,
    dismissInstall,
    handleBeforeInstallPrompt,
    handleControllerChange,
    handleMessage,
    lastSyncedAt,
    promptInstall,
    resetServiceWorkerState,
    setReloader,
    stale,
    trackRegistration,
    updateAvailable,
    type BeforeInstallPromptEvent,
} from '@/lib/serviceWorker';

/** Enregistrement minimal : seul `waiting` et l'écoute d'`updatefound` sont sollicités. */
const registrationWithWaiting = (waiting: { postMessage: (data: unknown) => void }) => ({
    waiting,
    installing: null,
    addEventListener: (): void => {},
}) as unknown as ServiceWorkerRegistration;

const installPromptEvent = (outcome: 'accepted' | 'dismissed'): BeforeInstallPromptEvent => ({
    preventDefault: vi.fn(),
    prompt: vi.fn().mockResolvedValue(undefined),
    userChoice: Promise.resolve({ outcome }),
}) as unknown as BeforeInstallPromptEvent;

beforeEach(() => {
    resetServiceWorkerState();
    localStorage.clear();
    setReloader(() => {});
});

describe('trackRegistration', () => {
    it('signale une mise à jour quand un worker attend déjà', () => {
        trackRegistration(registrationWithWaiting({ postMessage: vi.fn() }));

        expect(updateAvailable.value).toBe(true);
    });
});

describe('applyUpdate', () => {
    it('demande au worker en attente de prendre la main', () => {
        const postMessage = vi.fn();
        trackRegistration(registrationWithWaiting({ postMessage }));

        applyUpdate();

        expect(postMessage).toHaveBeenCalledWith({ type: 'SKIP_WAITING' });
    });

    it('ne recharge que le changement de contrôleur qu\'il a lui-même déclenché', () => {
        const reload = vi.fn();
        setReloader(reload);

        expect(handleControllerChange()).toBe(false);

        trackRegistration(registrationWithWaiting({ postMessage: vi.fn() }));
        applyUpdate();

        expect(handleControllerChange()).toBe(true);
        expect(reload).toHaveBeenCalledTimes(1);
    });

    it('ne recharge pas deux fois de suite', () => {
        const reload = vi.fn();
        setReloader(reload);
        trackRegistration(registrationWithWaiting({ postMessage: vi.fn() }));
        applyUpdate();

        handleControllerChange();
        handleControllerChange();

        expect(reload).toHaveBeenCalledTimes(1);
    });
});

describe('handleMessage', () => {
    it('marque les données comme périmées et retient leur horodatage', () => {
        handleMessage({ type: 'SERVED_STALE', cachedAt: 1_760_000_000_000 });

        expect(stale.value).toBe(true);
        expect(lastSyncedAt.value).toBe(1_760_000_000_000);
    });

    it('efface la péremption dès qu\'une revalidation réussit', () => {
        handleMessage({ type: 'SERVED_STALE', cachedAt: 1_760_000_000_000 });

        handleMessage({ type: 'FRESH' });

        expect(stale.value).toBe(false);
    });
});

describe('invite d\'installation', () => {
    it('neutralise l\'événement natif et propose l\'installation', () => {
        const event = installPromptEvent('accepted');

        handleBeforeInstallPrompt(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(canInstall.value).toBe(true);
    });

    it('ne repropose rien après un refus mémorisé', () => {
        dismissInstall();

        handleBeforeInstallPrompt(installPromptEvent('accepted'));

        expect(canInstall.value).toBe(false);
    });

    it('mémorise un refus exprimé dans l\'invite native', async () => {
        handleBeforeInstallPrompt(installPromptEvent('dismissed'));

        await promptInstall();

        expect(canInstall.value).toBe(false);

        canInstall.value = false;
        handleBeforeInstallPrompt(installPromptEvent('accepted'));

        expect(canInstall.value).toBe(false);
    });
});
