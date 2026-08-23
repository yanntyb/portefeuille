import { createApp } from 'vue';
import AppServiceWorkerBanner from '@/components/AppServiceWorkerBanner.vue';
import { pinia } from '@/stores/pinia';
import { useServiceWorkerStore } from '@/stores/serviceWorker';

/**
 * Application Vue distincte : les pages Inertia sont des racines indépendantes, sans layout
 * partagé, donc le bandeau serait sinon à dupliquer dans chacune. Elle reçoit la même instance de
 * Pinia que l'application Inertia — sinon les deux liraient deux états séparés.
 */
export function mountServiceWorkerBanner(): void {
    const host = document.getElementById('pwa-banner');

    if (host === null) {
        return;
    }

    createApp(AppServiceWorkerBanner).use(pinia).mount(host);
    useServiceWorkerStore().register();
}
