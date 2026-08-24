import { defineStore } from 'pinia';
import { computed, ref, type ComputedRef, type Ref } from 'vue';
import { readSnapshot, writeSnapshot } from '@/lib/snapshotStorage';
import type {
    AssetClassListSnapshot,
    AssetPageSnapshot,
    DashboardSnapshot,
    PropertiesListSnapshot,
    PropertyPageSnapshot,
    Snapshot,
} from '@/lib/snapshotContract';

/** Le worker laisse passer cette URL : le store absorbe lui-même ses échecs (cf. `classifyRequest`). */
const SNAPSHOT_URL = '/instantane';

/**
 * Un blob retenu avant ce déploiement porte encore `instruments`/`crypto`, pas `classes`/`assets` :
 * IndexedDB n'a ni clé de schéma ni version, donc `hydrate()` doit lui-même savoir reconnaître la
 * forme qu'il vient de lire plutôt que de faire confiance à son type déclaré.
 */
function isCurrentShape(candidate: Snapshot | null): candidate is Snapshot {
    return candidate !== null && typeof candidate === 'object' && 'classes' in candidate;
}

export const useSnapshotStore = defineStore('snapshot', () => {
    const snapshot: Ref<Snapshot | null> = ref(null);
    const syncing: Ref<boolean> = ref(false);

    const generatedAt: ComputedRef<number | null> = computed(
        (): number | null => snapshot.value?.generatedAt ?? null,
    );

    const dashboard: ComputedRef<DashboardSnapshot | null> = computed(
        (): DashboardSnapshot | null => snapshot.value?.dashboard ?? null,
    );

    const propertiesList: ComputedRef<PropertiesListSnapshot | null> = computed(
        (): PropertiesListSnapshot | null => snapshot.value?.properties.list ?? null,
    );

    /** L'indexation se fait en mémoire : le blob entier est déjà chargé, une requête par page n'ajouterait qu'une latence. */
    function classList(key: string): AssetClassListSnapshot | null {
        return snapshot.value?.classes[key] ?? null;
    }

    function assetPage(id: string): AssetPageSnapshot | null {
        return snapshot.value?.assets[id] ?? null;
    }

    function propertyPage(id: string): PropertyPageSnapshot | null {
        return snapshot.value?.properties.byId[id] ?? null;
    }

    /** Transitoires : lus par les pages que les Tasks 8 et 9 remplacent, supprimés avec elles. */
    const instrumentsList: ComputedRef<AssetClassListSnapshot | null> = computed(
        (): AssetClassListSnapshot | null => classList('equity'),
    );

    const cryptoList: ComputedRef<AssetClassListSnapshot | null> = computed(
        (): AssetClassListSnapshot | null => classList('crypto'),
    );

    const instrumentPage = assetPage;
    const cryptoPage = assetPage;

    /**
     * Lecture du blob retenu. Asynchrone, donc jamais dans le chemin du premier rendu.
     *
     * Un blob d'ancienne forme (`instruments`/`crypto`, sans `classes`) est rejeté plutôt que
     * gardé à moitié compris : le store repart de rien, et `sync()` le resynchronise à la
     * prochaine occasion — préférable à des accès défensifs partout où `classes`/`assets` sont lus.
     */
    async function hydrate(): Promise<void> {
        if (snapshot.value !== null) {
            return;
        }

        const stored = await readSnapshot();

        snapshot.value = isCurrentShape(stored) ? stored : null;
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
        classList,
        assetPage,
        propertiesList,
        instrumentsList,
        cryptoList,
        instrumentPage,
        cryptoPage,
        propertyPage,
        hydrate,
        sync,
    };
});
