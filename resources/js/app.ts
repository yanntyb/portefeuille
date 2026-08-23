import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, type DefineComponent } from 'vue';
import { mountServiceWorkerBanner } from '@/pwa/banner';
import { pinia } from '@/stores/pinia';
import { useSnapshotStore } from '@/stores/snapshot';
import { useThemeStore } from '@/stores/theme';

const appName = import.meta.env.VITE_APP_NAME ?? 'Laravel';

useThemeStore().apply();
mountServiceWorkerBanner();

/**
 * Hydratation puis resynchronisation, dans cet ordre : le blob retenu doit être en mémoire avant
 * que la réponse réseau ne le remplace, sinon une resynchronisation rapide écrirait par-dessus
 * rien et le premier rendu n'aurait aucune valeur d'avance.
 */
const snapshot = useSnapshotStore();

void snapshot.hydrate().then((): Promise<void> => snapshot.sync());

/**
 * Même déclencheur que les contrôles de mise à jour du worker, et pour la même raison : Inertia ne
 * fait aucune vraie navigation, donc rien ne rafraîchit l'instantané de lui-même. `inertia:navigate`
 * est volontairement écarté — une resynchronisation complète à chaque clic coûterait bien plus
 * qu'elle ne rapporte.
 */
document.addEventListener('visibilitychange', (): void => {
    if (document.visibilityState === 'visible') {
        void snapshot.sync();
    }
});

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
