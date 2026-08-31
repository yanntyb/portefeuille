import { describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, ref } from 'vue';
import type { Ref } from 'vue';
import SearchSelect from '@/components/ui/search-select/SearchSelect.vue';
import type { SelectOption } from '@/components/ui/search-select';

const options: SelectOption[] = [
    { value: '3', label: 'Société Générale' },
    { value: '4', label: 'PEA' },
];

type Mounted = { host: HTMLElement; chosen: ReturnType<typeof vi.fn>; changed: ReturnType<typeof vi.fn> };

function mountSelect(props: Record<string, unknown> = {}): Mounted {
    const chosen = vi.fn();
    const changed = vi.fn();
    const host = document.createElement('div');
    document.body.append(host);

    createApp(SearchSelect, {
        options,
        modelValue: '',
        'onUpdate:modelValue': chosen,
        onChange: changed,
        ...props,
    }).mount(host);

    return { host, chosen, changed };
}

const input = (host: HTMLElement): HTMLInputElement => host.querySelector('input') as HTMLInputElement;

const entries = (host: HTMLElement): (string | undefined)[] =>
    [...host.querySelectorAll('[role="option"]')].map((option) => option.textContent?.trim());

/** Le pilotage passe par les événements que reka écoute, jamais par ses composants internes. */
async function open(host: HTMLElement): Promise<void> {
    input(host).dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
    await settle();
}

async function search(host: HTMLElement, text: string): Promise<void> {
    input(host).value = text;
    input(host).dispatchEvent(new Event('input', { bubbles: true }));
    await settle();
}

/** Trois cycles : l'ouverture est asynchrone chez reka, et `Presence` en consomme un de plus. */
async function settle(): Promise<void> {
    await nextTick();
    await nextTick();
    await nextTick();
}

describe('SearchSelect', () => {
    it('ne déroule rien tant qu\'on ne l\'ouvre pas', async () => {
        const { host } = mountSelect();
        await settle();

        expect(host.querySelector('[role="listbox"]')).toBeNull();
    });

    it('rend une entrée par option une fois ouvert', async () => {
        const { host } = mountSelect();
        await open(host);

        expect(entries(host)).toEqual(['Société Générale', 'PEA']);
    });

    it('cherche sans se soucier des accents ni de la casse', async () => {
        const { host } = mountSelect();
        await open(host);
        await search(host, 'societe');

        /** Ce qu'aucun `<select>` ni `<datalist>` ne sait faire : « societe » trouve « Société ». */
        expect(entries(host)).toEqual(['Société Générale']);
    });

    it('dit qu\'il n\'a rien trouvé plutôt que de proposer d\'en créer un', async () => {
        const { host } = mountSelect({ empty: 'Aucun instrument' });
        await open(host);
        await search(host, 'zzz');

        expect(entries(host)).toEqual([]);
        expect(host.querySelector('[data-search-select-empty]')?.textContent?.trim()).toBe('Aucun instrument');
    });

    it('remonte la valeur choisie et signale le choix', async () => {
        const { host, chosen, changed } = mountSelect();
        await open(host);

        (host.querySelector('[data-search-select-option="4"]') as HTMLElement).click();
        await settle();

        expect(chosen).toHaveBeenCalledWith('4');
        expect(changed).toHaveBeenCalledTimes(1);
        expect(changed).toHaveBeenCalledWith('4');
    });

    it('ne signale rien quand on re-choisit la valeur déjà retenue', async () => {
        const { host, changed } = mountSelect({ modelValue: '4' });
        await open(host);

        (host.querySelector('[data-search-select-option="4"]') as HTMLElement).click();
        await settle();

        expect(changed).not.toHaveBeenCalled();
    });

    it('repart d\'une recherche neuve après un choix', async () => {
        /** Sans écouteur sur le modèle, `defineModel` le tient lui-même : l'intitulé se repose. */
        const host = document.createElement('div');
        document.body.append(host);
        createApp(SearchSelect, { options, modelValue: '' }).mount(host);
        await settle();
        await open(host);

        (host.querySelector('[data-search-select-option="3"]') as HTMLElement).click();
        await settle();

        /**
         * Le champ montre l'intitulé retenu, et reka prend sa valeur entière pour terme de
         * recherche : sans la sélection du texte, la frappe suivante s'y collerait et ne
         * trouverait plus rien.
         */
        expect(input(host).selectionStart).toBe(0);
        expect(input(host).selectionEnd).toBe('Société Générale'.length);
    });

    it('affiche l\'intitulé de la valeur déjà choisie', async () => {
        const { host } = mountSelect({ modelValue: '4' });
        await settle();

        expect(input(host).value).toBe('PEA');
    });

    it('retrouve l\'intitulé quand les options arrivent après la valeur', async () => {
        const late: Ref<SelectOption[]> = ref([]);
        const host = document.createElement('div');
        document.body.append(host);

        createApp({
            render: () => h(SearchSelect, { options: late.value, modelValue: '4' }),
        }).mount(host);
        await settle();

        /** Le cas réel : le catalogue vient d'un `fetch` postérieur au montage de la modale. */
        expect(input(host).value).toBe('');

        late.value = options;
        await settle();

        expect(input(host).value).toBe('PEA');
    });

    it('n\'envoie pas le formulaire quand on valide une recherche infructueuse', async () => {
        const submitted = vi.fn();
        const form = document.createElement('form');
        const host = document.createElement('div');
        form.append(host);
        document.body.append(form);
        form.addEventListener('submit', submitted);

        createApp(SearchSelect, { options, modelValue: '' }).mount(host);
        await settle();
        await open(host);
        await search(host, 'zzz');

        const enter = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true });
        input(host).dispatchEvent(enter);

        expect(enter.defaultPrevented).toBe(true);
        expect(submitted).not.toHaveBeenCalled();
    });

    it('annonce son invalidité et le message qui l\'explique', async () => {
        const { host } = mountSelect({ invalid: true, ariaDescribedby: 'champ-error' });
        await settle();

        expect(input(host).getAttribute('aria-invalid')).toBe('true');
        /** Sur l'`<input>` lui-même : sur la racine, aucun lecteur d'écran ne le lirait. */
        expect(input(host).getAttribute('aria-describedby')).toBe('champ-error');
    });

    it('n\'annonce rien quand il est valide', async () => {
        const { host } = mountSelect();
        await settle();

        expect(input(host).hasAttribute('aria-invalid')).toBe(false);
    });

    it('se désactive quand on le lui demande', async () => {
        const { host } = mountSelect({ disabled: true });
        await settle();

        expect(input(host).disabled).toBe(true);
    });
});
