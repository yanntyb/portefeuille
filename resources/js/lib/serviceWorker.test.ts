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
    requestStatus,
    resetServiceWorkerState,
    setReloader,
    stale,
    trackRegistration,
    updateAvailable,
    type BeforeInstallPromptEvent,
} from '@/lib/serviceWorker';

/** Enregistrement minimal : seul `waiting` et l'écoute d'`updatefound` sont sollicités. */
const registrationWithWaiting = (waiting: { postMessage: (data: unknown) => void }): ServiceWorkerRegistration => ({
    waiting,
    installing: null,
    addEventListener: (): void => {},
}) as unknown as ServiceWorkerRegistration;

/**
 * Enregistrement complet : retient le listener `updatefound` du registration et le listener
 * `statechange` du worker `installing`, pour rejouer la détection de mise à jour comme le ferait
 * le navigateur pendant qu'un onglet reste ouvert.
 */
const registrationWithInstalling = (): {
    registration: ServiceWorkerRegistration;
    installing: { postMessage: (data: unknown) => void; state: string };
    fireUpdateFound: () => void;
    fireStateChange: () => void;
} => {
    let updateFoundListener: (() => void) | null = null;
    let stateChangeListener: (() => void) | null = null;

    const installing = {
        postMessage: vi.fn(),
        state: 'installing',
        addEventListener: (type: string, listener: () => void): void => {
            if (type === 'statechange') {
                stateChangeListener = listener;
            }
        },
    };

    const registration = {
        waiting: null,
        installing,
        addEventListener: (type: string, listener: () => void): void => {
            if (type === 'updatefound') {
                updateFoundListener = listener;
            }
        },
    } as unknown as ServiceWorkerRegistration;

    return {
        registration,
        installing,
        fireUpdateFound: (): void => updateFoundListener?.(),
        fireStateChange: (): void => stateChangeListener?.(),
    };
};

/** Pose `navigator.serviceWorker.controller` : `null` signale la toute première installation. */
const withController = (controller: object | null): void => {
    Object.defineProperty(navigator, 'serviceWorker', {
        configurable: true,
        value: { controller },
    });
};

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

    it('détecte une mise à jour installée pendant que l\'onglet reste ouvert, sous contrôle', () => {
        withController({});
        const { registration, installing, fireUpdateFound, fireStateChange } = registrationWithInstalling();

        trackRegistration(registration);
        fireUpdateFound();
        installing.state = 'installed';
        fireStateChange();

        expect(updateAvailable.value).toBe(true);

        applyUpdate();

        expect(installing.postMessage).toHaveBeenCalledWith({ type: 'SKIP_WAITING' });
    });

    it('ne propose rien pour la toute première installation, faute de contrôleur', () => {
        withController(null);
        const { registration, installing, fireUpdateFound, fireStateChange } = registrationWithInstalling();

        trackRegistration(registration);
        fireUpdateFound();
        installing.state = 'installed';
        fireStateChange();

        expect(updateAvailable.value).toBe(false);
    });

    it('ne propose rien si le worker en installation n\'atteint pas l\'état « installed »', () => {
        withController({});
        const { registration, installing, fireUpdateFound, fireStateChange } = registrationWithInstalling();

        trackRegistration(registration);
        fireUpdateFound();
        installing.state = 'activating';
        fireStateChange();

        expect(updateAvailable.value).toBe(false);
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

    it('accepte une péremption sans horodatage connu', () => {
        handleMessage({ type: 'SERVED_STALE', cachedAt: null });

        expect(stale.value).toBe(true);
        expect(lastSyncedAt.value).toBeNull();
    });
});

describe('requestStatus', () => {
    it('interroge le worker qui contrôle déjà la page', () => {
        const postMessage = vi.fn();

        requestStatus({ postMessage } as unknown as ServiceWorker);

        expect(postMessage).toHaveBeenCalledWith({ type: 'REQUEST_STATUS' });
    });

    it('ne fait rien à la toute première installation, faute de contrôleur', () => {
        expect(() => requestStatus(null)).not.toThrow();
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
