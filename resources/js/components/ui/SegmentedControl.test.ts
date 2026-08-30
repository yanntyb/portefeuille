import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import SegmentedControl, { type Segment } from '@/components/ui/SegmentedControl.vue';

const segments: Segment[] = [
    { value: 'valuation', label: 'Valorisation' },
    { value: 'price', label: 'Cours' },
];

type Mounted = { host: HTMLElement; chosen: ReturnType<typeof vi.fn> };

function mountControl(modelValue: string, override: Segment[] = segments): Mounted {
    const chosen = vi.fn();
    const host = document.createElement('div');
    document.body.append(host);

    createApp(SegmentedControl, {
        modelValue,
        segments: override,
        label: 'Série tracée',
        'onUpdate:modelValue': chosen,
    }).mount(host);

    return { host, chosen };
}

const segment = (host: HTMLElement, value: string): HTMLButtonElement =>
    host.querySelector(`[data-segment="${value}"]`) as HTMLButtonElement;

function pressArrow(host: HTMLElement, value: string, key: string): void {
    segment(host, value).dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }));
}

beforeEach((): void => {
    document.body.innerHTML = '';
});

describe('choix au pointeur', () => {
    it('rend le segment cliqué à son parent', () => {
        const { host, chosen } = mountControl('valuation');

        segment(host, 'price').click();

        expect(chosen).toHaveBeenCalledExactlyOnceWith('price');
    });

    it('ne rend rien pour un segment désactivé', () => {
        const { host, chosen } = mountControl('valuation', [
            segments[0],
            { ...segments[1], disabled: true },
        ]);

        segment(host, 'price').click();

        expect(chosen).not.toHaveBeenCalled();
    });
});

describe('parcours au clavier', () => {
    it('passe au segment suivant sur flèche droite', () => {
        const { host, chosen } = mountControl('valuation');

        pressArrow(host, 'valuation', 'ArrowRight');

        expect(chosen).toHaveBeenCalledExactlyOnceWith('price');
    });

    it('revient au segment précédent sur flèche gauche', () => {
        const { host, chosen } = mountControl('price');

        pressArrow(host, 'price', 'ArrowLeft');

        expect(chosen).toHaveBeenCalledExactlyOnceWith('valuation');
    });

    it('boucle d\'un bout à l\'autre plutôt que de buter sur le bord', () => {
        const { host, chosen } = mountControl('price');

        pressArrow(host, 'price', 'ArrowRight');

        expect(chosen).toHaveBeenCalledExactlyOnceWith('valuation');
    });

    it('saute le segment désactivé au lieu de s\'y arrêter', () => {
        const { host, chosen } = mountControl('valuation', [
            segments[0],
            { ...segments[1], disabled: true },
        ]);

        pressArrow(host, 'valuation', 'ArrowRight');

        expect(chosen).not.toHaveBeenCalled();
    });

    it('ne laisse au clavier que le segment actif, les flèches parcourant les autres', () => {
        const { host } = mountControl('valuation');

        expect(segment(host, 'valuation').tabIndex).toBe(0);
        expect(segment(host, 'price').tabIndex).toBe(-1);
    });
});

describe('rendu', () => {
    it('marque le segment actif pour les technologies d\'assistance', async () => {
        const { host } = mountControl('price');
        await nextTick();

        expect(segment(host, 'price').getAttribute('aria-selected')).toBe('true');
        expect(segment(host, 'valuation').getAttribute('aria-selected')).toBe('false');
    });
});
