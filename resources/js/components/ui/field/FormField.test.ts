import { describe, expect, it } from 'vitest';
import { createApp, h, nextTick } from 'vue';
import FormField from '@/components/ui/field/FormField.vue';

type Props = { id: string; label: string; error?: string | null; hint?: string };

/** Monte le cadre autour d'un vrai `<input>`, seul moyen de vérifier ce qu'il lui transmet. */
function mountField(props: Props): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp({
        render: () =>
            h(FormField, props, {
                default: ({ describedBy, invalid }: { describedBy?: string; invalid: boolean }) =>
                    h('input', {
                        id: props.id,
                        'aria-describedby': describedBy,
                        'aria-invalid': invalid || undefined,
                    }),
            }),
    }).mount(host);

    return host;
}

describe('FormField', () => {
    it('lie son intitulé au contrôle du slot', async () => {
        const host = mountField({ id: 'quantite', label: 'Quantité' });
        await nextTick();

        const label = host.querySelector('label') as HTMLLabelElement;

        expect(label.textContent?.trim()).toBe('Quantité');
        expect(label.getAttribute('for')).toBe('quantite');
    });

    it('rend l\'erreur du serveur et la fait désigner par le contrôle', async () => {
        const host = mountField({ id: 'quantite', label: 'Quantité', error: 'Vous ne détenez que 4 titre(s).' });
        await nextTick();

        const error = host.querySelector('[data-field-error]') as HTMLElement;
        const input = host.querySelector('input') as HTMLInputElement;

        expect(error.textContent?.trim()).toBe('Vous ne détenez que 4 titre(s).');
        expect(error.id).toBe('quantite-error');
        expect(input.getAttribute('aria-describedby')).toBe('quantite-error');
        expect(input.getAttribute('aria-invalid')).toBe('true');
    });

    it('n\'annonce ni erreur ni invalidité quand il n\'y en a pas', async () => {
        const host = mountField({ id: 'frais', label: 'Frais' });
        await nextTick();

        expect(host.querySelector('[data-field-error]')).toBeNull();
        expect((host.querySelector('input') as HTMLInputElement).hasAttribute('aria-invalid')).toBe(false);
    });

    it('efface la précision au profit de l\'erreur, jamais les deux à la fois', async () => {
        const host = mountField({ id: 'prix', label: 'Prix unitaire', hint: 'En euros', error: 'Prix requis.' });
        await nextTick();

        /** Deux messages sous un champ se liraient comme deux exigences distinctes. */
        expect(host.textContent).toContain('Prix requis.');
        expect(host.textContent).not.toContain('En euros');
        expect((host.querySelector('input') as HTMLInputElement).getAttribute('aria-describedby'))
            .toBe('prix-error');
    });

    it('fait désigner la précision par le contrôle en l\'absence d\'erreur', async () => {
        const host = mountField({ id: 'prix', label: 'Prix unitaire', hint: 'En euros' });
        await nextTick();

        expect((host.querySelector('input') as HTMLInputElement).getAttribute('aria-describedby'))
            .toBe('prix-hint');
    });
});
