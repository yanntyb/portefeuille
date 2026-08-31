import { describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import NativeSelect, { type SelectOption } from '@/components/ui/native-select/NativeSelect.vue';

const options: SelectOption[] = [
    { value: '3', label: 'Compte-titres' },
    { value: '4', label: 'PEA' },
];

type Mounted = { host: HTMLElement; chosen: ReturnType<typeof vi.fn> };

function mountSelect(props: Record<string, unknown> = {}): Mounted {
    const chosen = vi.fn();
    const host = document.createElement('div');
    document.body.append(host);

    createApp(NativeSelect, {
        options,
        modelValue: '',
        'onUpdate:modelValue': chosen,
        ...props,
    }).mount(host);

    return { host, chosen };
}

const select = (host: HTMLElement): HTMLSelectElement =>
    host.querySelector('select') as HTMLSelectElement;

describe('NativeSelect', () => {
    it('rend une option par entrée', async () => {
        const { host } = mountSelect();
        await nextTick();

        expect([...select(host).options].map((option) => option.textContent?.trim())).toEqual([
            'Compte-titres',
            'PEA',
        ]);
    });

    it('place un premier choix inerte quand rien n\'est encore choisi', async () => {
        const { host } = mountSelect({ placeholder: 'Choisir une enveloppe' });
        await nextTick();

        const first = select(host).options[0];

        /** Inerte et non masqué : il dit ce qu'on attend, il ne peut pas être renvoyé. */
        expect(first.textContent?.trim()).toBe('Choisir une enveloppe');
        expect(first.disabled).toBe(true);
        expect(first.value).toBe('');
    });

    it('remonte la valeur choisie', async () => {
        const { host, chosen } = mountSelect();
        await nextTick();

        select(host).value = '4';
        select(host).dispatchEvent(new Event('change'));

        expect(chosen).toHaveBeenCalledWith('4');
    });

    it('annonce son invalidité au lecteur d\'écran', async () => {
        const { host } = mountSelect({ invalid: true });
        await nextTick();

        expect(select(host).getAttribute('aria-invalid')).toBe('true');
    });

    it('n\'annonce rien quand il est valide', async () => {
        const { host } = mountSelect();
        await nextTick();

        expect(select(host).hasAttribute('aria-invalid')).toBe(false);
    });

    it('se désactive quand on le lui demande', async () => {
        const { host } = mountSelect({ disabled: true });
        await nextTick();

        expect(select(host).disabled).toBe(true);
    });
});
