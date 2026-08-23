import { createStore, get, set, type UseStore } from 'idb-keyval';
import type { Snapshot } from './snapshotContract';

/**
 * Un blob unique, pas des object stores indexés : à ce volume — quelques dizaines d'actifs, trois
 * biens — un schéma client et ses migrations coûteraient plus qu'ils ne rapportent. Ce module est
 * la seule frontière à franchir le jour où ce ne sera plus vrai.
 */
const SNAPSHOT_KEY = 'snapshot';

const store: UseStore = createStore('argent', 'pwa');

/**
 * Lecture best-effort : IndexedDB est indisponible en navigation privée sur certains navigateurs,
 * et un quota dépassé fait rejeter la transaction. Aucun de ces cas ne doit empêcher la page de
 * s'afficher — elle retombe alors sur le comportement d'avant l'instantané.
 */
export async function readSnapshot(): Promise<Snapshot | null> {
    try {
        return (await get<Snapshot>(SNAPSHOT_KEY, store)) ?? null;
    } catch {
        return null;
    }
}

/** Écriture best-effort, pour les mêmes raisons que la lecture. */
export async function writeSnapshot(snapshot: Snapshot): Promise<void> {
    try {
        await set(SNAPSHOT_KEY, snapshot, store);
    } catch {
        /* Best-effort : voir le commentaire de `readSnapshot`. */
    }
}
