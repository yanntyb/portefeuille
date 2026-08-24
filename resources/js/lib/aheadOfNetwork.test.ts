import { describe, expect, it } from 'vitest';
import { ref } from 'vue';
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
