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
