import { ref, type Ref } from 'vue';

/** Type non standardisé, absent de la bibliothèque DOM, mais implémenté par Chrome. */
export type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

export type SwClientMessage =
    | { type: 'FRESH' }
    | { type: 'SERVED_STALE'; cachedAt: number | null };

/** Un refus est définitif : l'invite d'installation ne se représente pas à chaque visite. */
const DISMISSED_KEY = 'pwa-install-dismissed';

export const updateAvailable: Ref<boolean> = ref(false);
export const stale: Ref<boolean> = ref(false);
export const lastSyncedAt: Ref<number | null> = ref(null);
export const canInstall: Ref<boolean> = ref(false);

let waitingWorker: ServiceWorker | null = null;
let installEvent: BeforeInstallPromptEvent | null = null;

/** Vrai seulement entre notre `SKIP_WAITING` et le `controllerchange` qui en découle. */
let awaitingReload = false;

let reload: () => void = (): void => {
    window.location.reload();
};

/** Point d'injection : happy-dom n'a pas de vraie navigation à recharger. */
export function setReloader(fn: () => void): void {
    reload = fn;
}

/**
 * Remet le module dans son état initial. Réservé aux tests : l'état de module survit d'un test à
 * l'autre, et une garde comme `awaitingReload` laissée armée fausserait le test suivant.
 */
export function resetServiceWorkerState(): void {
    updateAvailable.value = false;
    stale.value = false;
    lastSyncedAt.value = null;
    canInstall.value = false;
    waitingWorker = null;
    installEvent = null;
    awaitingReload = false;
}

export function trackRegistration(registration: ServiceWorkerRegistration): void {
    if (registration.waiting !== null) {
        waitingWorker = registration.waiting;
        updateAvailable.value = true;
    }

    registration.addEventListener('updatefound', (): void => {
        const installing = registration.installing;

        if (installing === null) {
            return;
        }

        installing.addEventListener('statechange', (): void => {
            /** Sans contrôleur, c'est la toute première installation : rien à proposer. */
            if (installing.state !== 'installed' || navigator.serviceWorker.controller === null) {
                return;
            }

            waitingWorker = installing;
            updateAvailable.value = true;
        });
    });
}

export function handleMessage(message: SwClientMessage): void {
    if (message.type === 'FRESH') {
        stale.value = false;
        lastSyncedAt.value = Date.now();

        return;
    }

    stale.value = true;
    lastSyncedAt.value = message.cachedAt;
}

/** Renvoie vrai si un rechargement a été demandé — valeur de retour destinée aux tests. */
export function handleControllerChange(): boolean {
    if (!awaitingReload) {
        return false;
    }

    awaitingReload = false;
    reload();

    return true;
}

export function applyUpdate(): void {
    if (waitingWorker === null) {
        return;
    }

    awaitingReload = true;
    waitingWorker.postMessage({ type: 'SKIP_WAITING' });
}

export function handleBeforeInstallPrompt(event: BeforeInstallPromptEvent): void {
    event.preventDefault();
    installEvent = event;
    canInstall.value = localStorage.getItem(DISMISSED_KEY) === null;
}

export async function promptInstall(): Promise<void> {
    if (installEvent === null) {
        return;
    }

    await installEvent.prompt();
    const { outcome } = await installEvent.userChoice;

    if (outcome === 'dismissed') {
        localStorage.setItem(DISMISSED_KEY, '1');
    }

    installEvent = null;
    canInstall.value = false;
}

export function dismissInstall(): void {
    localStorage.setItem(DISMISSED_KEY, '1');
    canInstall.value = false;
}

/**
 * Inertia ne fait aucune vraie navigation, donc le navigateur ne re-télécharge jamais `/sw.js`
 * de lui-même : sans ces deux déclencheurs, une application installée peut rester des jours
 * sur l'ancienne version.
 */
function scheduleUpdateChecks(registration: ServiceWorkerRegistration): void {
    document.addEventListener('visibilitychange', (): void => {
        if (document.visibilityState === 'visible') {
            void registration.update();
        }
    });

    document.addEventListener('inertia:navigate', (): void => {
        void registration.update();
    });
}

export function registerServiceWorker(): void {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('beforeinstallprompt', (event: Event): void => {
        handleBeforeInstallPrompt(event as BeforeInstallPromptEvent);
    });

    navigator.serviceWorker.addEventListener('message', (event: MessageEvent): void => {
        handleMessage(event.data as SwClientMessage);
    });

    navigator.serviceWorker.addEventListener('controllerchange', (): void => {
        handleControllerChange();
    });

    void navigator.serviceWorker
        .register('/sw.js')
        .then((registration: ServiceWorkerRegistration): void => {
            trackRegistration(registration);
            scheduleUpdateChecks(registration);
        });
}
