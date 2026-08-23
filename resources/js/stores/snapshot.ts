import { defineStore } from 'pinia';
import { computed, ref, type ComputedRef, type Ref } from 'vue';
import { readSnapshot, writeSnapshot } from '@/lib/snapshotStorage';
import type {
    CryptoListSnapshot,
    DashboardSnapshot,
    InstrumentPageSnapshot,
    InstrumentsListSnapshot,
    PropertiesListSnapshot,
    PropertyPageSnapshot,
    Snapshot,
} from '@/lib/snapshotContract';

/** Le worker laisse passer cette URL : le store absorbe lui-même ses échecs (cf. `classifyRequest`). */
const SNAPSHOT_URL = '/instantane';

export const useSnapshotStore = defineStore('snapshot', () => {
    const snapshot: Ref<Snapshot | null> = ref(null);
    const syncing: Ref<boolean> = ref(false);

    const generatedAt: ComputedRef<number | null> = computed(
        (): number | null => snapshot.value?.generatedAt ?? null,
    );

    const dashboard: ComputedRef<DashboardSnapshot | null> = computed(
        (): DashboardSnapshot | null => snapshot.value?.dashboard ?? null,
    );

    const instrumentsList: ComputedRef<InstrumentsListSnapshot | null> = computed(
        (): InstrumentsListSnapshot | null => snapshot.value?.instruments.list ?? null,
    );

    const cryptoList: ComputedRef<CryptoListSnapshot | null> = computed(
        (): CryptoListSnapshot | null => snapshot.value?.crypto.list ?? null,
    );

    const propertiesList: ComputedRef<PropertiesListSnapshot | null> = computed(
        (): PropertiesListSnapshot | null => snapshot.value?.properties.list ?? null,
    );

    /**
     * L'indexation par entité se fait en mémoire : le blob entier est déjà chargé, une requête
     * IndexedDB par page n'apporterait qu'une latence.
     */
    function instrumentPage(id: string): InstrumentPageSnapshot | null {
        return snapshot.value?.instruments.byId[id] ?? null;
    }

    function cryptoPage(id: string): InstrumentPageSnapshot | null {
        return snapshot.value?.crypto.byId[id] ?? null;
    }

    function propertyPage(id: string): PropertyPageSnapshot | null {
        return snapshot.value?.properties.byId[id] ?? null;
    }

    /** Lecture du blob retenu. Asynchrone, donc jamais dans le chemin du premier rendu. */
    async function hydrate(): Promise<void> {
        if (snapshot.value !== null) {
            return;
        }

        snapshot.value = await readSnapshot();
    }

    /**
     * Resynchronisation de fond. Tout échec est silencieux : le lecteur garde ce qu'il avait, et
     * aucun bandeau ne bascule — cette requête ne décrit pas la page qu'il regarde.
     */
    async function sync(): Promise<void> {
        if (syncing.value) {
            return;
        }

        syncing.value = true;

        try {
            const response = await fetch(SNAPSHOT_URL, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                return;
            }

            const fresh = (await response.json()) as Snapshot;

            /** Contenu identique : ni remplacement en mémoire, ni écriture IndexedDB. */
            if (fresh.version === snapshot.value?.version) {
                return;
            }

            snapshot.value = fresh;
            await writeSnapshot(fresh);
        } catch {
            /* Silencieux : voir le commentaire de la fonction. */
        } finally {
            syncing.value = false;
        }
    }

    return {
        snapshot,
        syncing,
        generatedAt,
        dashboard,
        instrumentsList,
        cryptoList,
        propertiesList,
        instrumentPage,
        cryptoPage,
        propertyPage,
        hydrate,
        sync,
    };
});
