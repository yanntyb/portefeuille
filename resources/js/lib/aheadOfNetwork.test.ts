import { describe, expect, it } from 'vitest';
import { ref, shallowRef } from 'vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';

describe('fusion prop / instantané', () => {
    it('préfère la prop Inertia dès qu\'elle est là', () => {
        const merged = aheadOfNetwork(() => 'réseau', () => 'store');

        expect(merged.value).toBe('réseau');
    });

    it('retombe sur l\'instantané tant que la prop manque', () => {
        const merged = aheadOfNetwork(() => undefined, () => 'store');

        expect(merged.value).toBe('store');
    });

    it('rend null quand ni l\'un ni l\'autre n\'existe', () => {
        const merged = aheadOfNetwork(() => undefined, () => null);

        expect(merged.value).toBeNull();
    });

    it('bascule sur la prop dès son arrivée', () => {
        const prop = ref<string | undefined>(undefined);
        const merged = aheadOfNetwork(() => prop.value, () => 'store');

        expect(merged.value).toBe('store');

        prop.value = 'réseau';

        expect(merged.value).toBe('réseau');
    });
});

describe('verrou sur le premier instantané rendu', () => {
    it('ignore un instantané resynchronisé sous les pieds du lecteur', () => {
        const stored = ref<string | null>('blob retenu');
        const merged = aheadOfNetwork<string>(() => undefined, () => stored.value);

        expect(merged.value).toBe('blob retenu');

        stored.value = 'blob resynchronisé';

        expect(merged.value).toBe('blob retenu');
    });

    it('attend le premier instantané non nul plutôt que de se verrouiller sur rien', () => {
        const stored = ref<string | null>(null);
        const merged = aheadOfNetwork<string>(() => undefined, () => stored.value);

        expect(merged.value).toBeNull();

        stored.value = 'blob retenu';

        expect(merged.value).toBe('blob retenu');
    });

    it('laisse la prop Inertia passer devant l\'instantané verrouillé', () => {
        const prop = ref<string | undefined>(undefined);
        const stored = ref<string | null>('blob retenu');
        const merged = aheadOfNetwork<string>(() => prop.value, () => stored.value);

        expect(merged.value).toBe('blob retenu');

        prop.value = 'réseau';

        expect(merged.value).toBe('réseau');
    });
});

describe('stabilité de la référence rendue', () => {
    type Series = { labels: string[]; values: number[] };

    it('garde la référence de l\'instantané quand le réseau livre les mêmes données', () => {
        const snapshot: Series = { labels: ['janv.'], values: [1] };
        const prop = shallowRef<Series | undefined>(undefined);
        const merged = aheadOfNetwork<Series>(() => prop.value, () => snapshot);

        expect(merged.value).toBe(snapshot);

        prop.value = { labels: ['janv.'], values: [1] };

        expect(merged.value).toBe(snapshot);
    });

    it('rend la valeur du réseau dès que les données diffèrent', () => {
        const snapshot: Series = { labels: ['janv.'], values: [1] };
        const fresh: Series = { labels: ['janv.', 'févr.'], values: [1, 2] };
        const prop = shallowRef<Series | undefined>(undefined);
        const merged = aheadOfNetwork<Series>(() => prop.value, () => snapshot);

        expect(merged.value).toBe(snapshot);

        prop.value = fresh;

        expect(merged.value).toBe(fresh);
    });

    it('garde la première réponse réseau quand la suivante lui est identique', () => {
        const first: Series = { labels: ['janv.'], values: [1] };
        const prop = shallowRef<Series | undefined>(first);
        const merged = aheadOfNetwork<Series>(() => prop.value, () => null);

        expect(merged.value).toBe(first);

        prop.value = { labels: ['janv.'], values: [1] };

        expect(merged.value).toBe(first);
    });
});
