import { createApp } from 'vue';
import AppServiceWorkerBanner from '@/components/AppServiceWorkerBanner.vue';
import { registerServiceWorker } from '@/lib/serviceWorker';

/**
 * Application Vue distincte : les pages Inertia sont des racines indépendantes, sans layout
 * partagé, donc le bandeau serait sinon à dupliquer dans chacune.
 */
export function mountServiceWorkerBanner(): void {
    const host = document.getElementById('pwa-banner');

    if (host === null) {
        return;
    }

    registerServiceWorker();
    createApp(AppServiceWorkerBanner).mount(host);
}
