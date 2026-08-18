/**
 * Node 26 définit un `globalThis.localStorage` expérimental qui masque celui de happy-dom : la
 * liste de clés que Vitest recopie depuis la fenêtre ne couvre pas `localStorage`, et le stub de
 * Node reste `undefined` sans `--localstorage-file`. On réinstalle donc un stockage en mémoire.
 */
class MemoryStorage implements Storage {
    private entries = new Map<string, string>();

    get length(): number {
        return this.entries.size;
    }

    clear(): void {
        this.entries.clear();
    }

    getItem(key: string): string | null {
        return this.entries.get(key) ?? null;
    }

    key(index: number): string | null {
        return [...this.entries.keys()][index] ?? null;
    }

    removeItem(key: string): void {
        this.entries.delete(key);
    }

    setItem(key: string, value: string): void {
        this.entries.set(key, String(value));
    }
}

Object.defineProperty(globalThis, 'localStorage', {
    configurable: true,
    value: new MemoryStorage(),
    writable: true,
});
