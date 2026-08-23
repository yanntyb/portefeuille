import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, type DefineComponent } from 'vue';
import { mountServiceWorkerBanner } from '@/pwa/banner';
import { pinia } from '@/stores/pinia';
import { useThemeStore } from '@/stores/theme';

const appName = import.meta.env.VITE_APP_NAME ?? 'Laravel';

useThemeStore().apply();
mountServiceWorkerBanner();

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .mount(el);
    },
    progress: {
        color: '#5257d6',
    },
    defaults: {
        prefetch: {
            hoverDelay: 75,
            cacheFor: ['30s', '5m'],
        },
    },
});
