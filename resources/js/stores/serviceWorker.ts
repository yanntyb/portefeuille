import { defineStore } from 'pinia';
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

export const useServiceWorkerStore = defineStore('serviceWorker', () => {
    const updateAvailable: Ref<boolean> = ref(false);
    const stale: Ref<boolean> = ref(false);
    const lastSyncedAt: Ref<number | null> = ref(null);
    const canInstall: Ref<boolean> = ref(false);

    let waitingWorker: ServiceWorker | null = null;
    let installEvent: BeforeInstallPromptEvent | null = null;

    /** Vrai seulement entre notre `SKIP_WAITING` et le `controllerchange` qui en découle. */
    let awaitingReload = false;

    let reload: () => void = (): void => {
        window.location.reload();
    };

    /** Point d'injection : happy-dom n'a pas de vraie navigation à recharger. */
    function setReloader(fn: () => void): void {
        reload = fn;
    }

    function trackRegistration(registration: ServiceWorkerRegistration): void {
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

    /**
     * Interroge le worker sur son dernier état de fraîcheur connu. Une diffusion `postMessage`
     * déclenchée pendant une navigation peut manquer sa cible — le client qui la reçoit est celui
     * qu'on est en train de quitter, pas la page qui vient de se charger — donc le client tire l'état
     * actuel à l'amorçage plutôt que de compter uniquement sur les diffusions à venir. `controller`
     * est `null` à la toute première installation : personne ne contrôle encore la page, rien à
     * demander.
     */
    function requestStatus(controller: ServiceWorker | null): void {
        if (controller === null) {
            return;
        }

        controller.postMessage({ type: 'REQUEST_STATUS' });
    }

    function handleMessage(message: SwClientMessage): void {
        if (message.type === 'FRESH') {
            stale.value = false;
            lastSyncedAt.value = Date.now();

            return;
        }

        stale.value = true;
        lastSyncedAt.value = message.cachedAt;
    }

    /** Renvoie vrai si un rechargement a été demandé — valeur de retour destinée aux tests. */
    function handleControllerChange(): boolean {
        if (!awaitingReload) {
            return false;
        }

        awaitingReload = false;
        reload();

        return true;
    }

    function applyUpdate(): void {
        if (waitingWorker === null) {
            return;
        }

        awaitingReload = true;
        waitingWorker.postMessage({ type: 'SKIP_WAITING' });
    }

    function handleBeforeInstallPrompt(event: BeforeInstallPromptEvent): void {
        event.preventDefault();
        installEvent = event;
        canInstall.value = localStorage.getItem(DISMISSED_KEY) === null;
    }

    async function promptInstall(): Promise<void> {
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

    function dismissInstall(): void {
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

    function register(): void {
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
                requestStatus(navigator.serviceWorker.controller);
            })
            /** Sans ce `catch`, un échec d'enregistrement ne remonte qu'en rejet non géré. */
            .catch((error: unknown): void => {
                console.error('Échec de l\'enregistrement du service worker.', error);
            });
    }

    return {
        updateAvailable, stale, lastSyncedAt, canInstall,
        setReloader, trackRegistration, requestStatus, handleMessage, handleControllerChange,
        applyUpdate, handleBeforeInstallPrompt, promptInstall, dismissInstall, register,
    };
});
