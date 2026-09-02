<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import type { Ref } from 'vue';
import { Input } from '@/components/ui/input';

/**
 * La recherche d'un instrument, en deux étapes, sans chrome de modale.
 *
 * Sans chrome pour une raison précise : la page catalogue l'enveloppe dans un `Dialog`, mais le
 * formulaire de transaction l'affiche **à la place** de ses champs, dans le `DialogContent` déjà
 * ouvert. Deux dialogues reka-ui superposés donneraient deux verrous de défilement, deux pièges de
 * focus et un `Échap` ambigu — c'est déjà la raison pour laquelle la confirmation de suppression
 * de `TransactionDialog` est un volet et non un second dialogue.
 */
type SearchResult = {
    symbol: string;
    name: string;
    exchange: string | null;
    type: string | null;
    typeLabel: string | null;
    existingId: number | null;
};

export type CreatedInstrument = {
    id: number;
    name: string;
    ticker: string | null;
    assetClass: string;
    assetClassSlug: string;
};

const props = withDefaults(defineProps<{ initialTerm?: string; exposure?: string | null }>(), {
    initialTerm: '',
    exposure: null,
});

const emit = defineEmits<{
    created: [instrument: CreatedInstrument];
    cancel: [];
    open: [payload: { id: number }];
}>();

/** Les deux enums, dupliqués côté client comme partout ailleurs dans les formulaires. */
const TYPES: { value: string; label: string }[] = [
    { value: 'stock', label: 'Action' },
    { value: 'etf', label: 'ETF' },
    { value: 'crypto', label: 'Cryptomonnaie' },
    { value: 'bond', label: 'Obligation' },
    { value: 'commodity', label: 'Matière première' },
];

/** Les quatre cas d'`AssetClass`, dans leur ordre — qui est un contrat côté PHP. */
const ASSET_CLASSES: { value: string; label: string }[] = [
    { value: 'equity', label: 'Actions' },
    { value: 'bond', label: 'Obligations' },
    { value: 'commodity', label: 'Matières premières' },
    { value: 'crypto', label: 'Crypto' },
];

/** L'exposition par défaut d'un type, jumelle d'`AssetClass::defaultForType()`. */
const defaultClassFor = (type: string): string =>
    ({ stock: 'equity', etf: 'equity', bond: 'bond', crypto: 'crypto', commodity: 'commodity' })[type] ?? 'equity';

const term: Ref<string> = ref(props.initialTerm);
const results: Ref<SearchResult[]> = ref([]);
const searching: Ref<boolean> = ref(false);
const failed: Ref<boolean> = ref(false);
const chosen: Ref<SearchResult | null> = ref(null);

/**
 * `useHttp` et non `fetch` : seul lui porte le jeton anti-contrefaçon, lu du cookie `XSRF-TOKEN` et
 * posé en en-tête `X-XSRF-TOKEN` par le client XHR — même client que `SyncButton.vue`, qui poste
 * déjà `/synchronisation` ainsi. Un `fetch` nu s'en passe : il n'échoue qu'hors des tests, où
 * `PreventRequestForgery` s'efface devant `runningUnitTests()`, ce qui masquait le 419 jusqu'ici.
 *
 * La recherche, elle, reste en `fetch` : une lecture ne porte pas ce jeton.
 */
type InstrumentDraft = { name: string; ticker: string; isin: string; type: string; assetClass: string };

const form = useHttp<InstrumentDraft, CreatedInstrument>({
    name: '',
    ticker: '',
    isin: '',
    type: 'stock',
    assetClass: 'equity',
});

/** Une chaîne vide n'est pas un ISIN absent : le serveur veut `null`, le champ veut du texte. */
form.transform((data): Record<string, unknown> => ({ ...data, isin: data.isin === '' ? null : data.isin }));

/** Distinct de `form.errors` : un 500 ou une coupure réseau n'ont pas de message par champ. */
const globalError: Ref<string | null> = ref(null);

let timer: ReturnType<typeof setTimeout> | null = null;
let inFlight: AbortController | null = null;

const search = async (value: string): Promise<void> => {
    inFlight?.abort();

    if (value.trim() === '') {
        results.value = [];

        return;
    }

    const controller = new AbortController();
    inFlight = controller;
    searching.value = true;
    failed.value = false;

    try {
        const response = await fetch(`/instruments/recherche?q=${encodeURIComponent(value.trim())}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

        if (!response.ok) {
            failed.value = true;

            return;
        }

        results.value = (await response.json()) as SearchResult[];
    } catch {
        /** Une requête annulée par la frappe suivante n'est pas un échec à montrer. */
        if (!controller.signal.aborted) {
            failed.value = true;
        }
    } finally {
        if (inFlight === controller) {
            searching.value = false;
        }
    }
};

/**
 * Le terme pré-rempli par la page catalogue cherche dès le montage, hors du débounce : sans quoi
 * la loupe « Chercher « nvidia » chez Yahoo » ouvre un panneau qui affiche « Aucun instrument ne
 * porte ce nom » avant même d'avoir interrogé qui que ce soit — le débounce n'a de sens que pour
 * étaler des frappes, pas pour retarder une valeur déjà connue au montage.
 */
if (props.initialTerm.trim() !== '') {
    void search(props.initialTerm);
}

/** Débouncé : la frappe ne doit pas ouvrir un process Python par lettre. */
watch(term, (value: string): void => {
    if (timer !== null) {
        clearTimeout(timer);
    }

    timer = setTimeout((): void => void search(value), 300);
});

onBeforeUnmount((): void => {
    if (timer !== null) {
        clearTimeout(timer);
    }

    inFlight?.abort();
});

const choose = (result: SearchResult): void => {
    if (result.existingId !== null) {
        emit('open', { id: result.existingId });

        return;
    }

    const type = result.type ?? 'stock';

    chosen.value = result;
    globalError.value = null;
    form.clearErrors();
    form.name = result.name;
    form.ticker = result.symbol;
    form.isin = '';
    form.type = type;
    /** L'exposition de la page prime : un instrument créé ailleurs disparaîtrait au retour. */
    form.assetClass = props.exposure ?? defaultClassFor(type);
};

const back = (): void => {
    chosen.value = null;
    globalError.value = null;
    form.clearErrors();
};

const submit = async (): Promise<void> => {
    /**
     * Garde synchrone, comme `blocked` dans `TransactionForm.vue` et `busy` dans `SyncButton.vue` :
     * `:disabled="form.processing"` protège la souris, pas un double appel synchrone de `submit()`
     * dans le même tick. La création n'a pas de garde-fou d'idempotence côté serveur — l'index
     * unique sur `assets.ticker` est volontairement différé — un double envoi créerait donc deux
     * lignes que rien ne rejetterait.
     */
    if (form.processing) {
        return;
    }

    globalError.value = null;

    try {
        await form.post('/instruments', {
            onSuccess: (instrument): void => emit('created', instrument),
            onHttpException: (): void => {
                globalError.value = "L'instrument n'a pas pu être créé.";
            },
            onNetworkError: (): void => {
                globalError.value = "L'instrument n'a pas pu être créé.";
            },
        });
    } catch {
        /** `onHttpException`/`onNetworkError` ont déjà posé le message ; le rejet ne doit pas remonter. */
    }
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <template v-if="chosen === null">
            <Input
                v-model="term"
                data-instrument-search-input
                type="search"
                aria-label="Chercher un instrument"
                placeholder="Nom ou ticker"
            />

            <p v-if="searching" data-instrument-searching class="text-sm text-muted-foreground">Recherche…</p>

            <p v-else-if="failed" data-instrument-failed class="text-sm text-destructive">
                La recherche n'a rien pu ramener. Réessaie dans un instant.
            </p>

            <ul v-else-if="results.length" class="flex flex-col">
                <li
                    v-for="result in results"
                    :key="result.existingId ?? result.symbol"
                    data-instrument-result
                    class="border-b border-separator last:border-b-0"
                >
                    <button type="button" class="flex w-full items-center gap-3 px-3 py-3 text-left hover:bg-muted" @click="choose(result)">
                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="truncate font-semibold">
                                {{ result.name }}
                                <span class="text-muted-foreground">({{ result.symbol }})</span>
                            </span>
                            <span class="flex items-center gap-2 text-xs text-muted-foreground">
                                <span v-if="result.typeLabel">{{ result.typeLabel }}</span>
                                <span v-if="result.exchange">{{ result.exchange }}</span>
                                <span v-if="result.existingId !== null" data-instrument-existing class="font-semibold text-subtle-foreground">
                                    Déjà suivi
                                </span>
                            </span>
                        </span>
                    </button>
                </li>
            </ul>

            <p v-else-if="term.trim() !== ''" data-instrument-none class="py-6 text-center text-sm text-muted-foreground">
                Aucun instrument ne porte ce nom.
            </p>

            <button type="button" data-instrument-cancel class="self-start text-sm text-muted-foreground" @click="emit('cancel')">
                Annuler
            </button>
        </template>

        <form v-else data-instrument-confirm class="flex flex-col gap-4" @submit.prevent="submit()">
            <label class="flex flex-col gap-1.5 text-sm font-medium">
                Nom
                <Input v-model="form.name" data-instrument-name type="text" />
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-medium">
                Ticker
                <Input v-model="form.ticker" data-instrument-ticker type="text" />
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-medium">
                ISIN
                <Input v-model="form.isin" data-instrument-isin type="text" />
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-medium">
                Type
                <select v-model="form.type" data-instrument-type class="rounded-md border border-input bg-background px-3 py-2 font-normal">
                    <option v-for="option in TYPES" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-medium">
                Exposition
                <select v-model="form.assetClass" data-instrument-asset-class class="rounded-md border border-input bg-background px-3 py-2 font-normal">
                    <option v-for="option in ASSET_CLASSES" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </label>

            <p
                v-if="globalError !== null || Object.keys(form.errors).length"
                data-instrument-error
                role="alert"
                class="text-xs text-destructive"
            >
                {{ globalError ?? Object.values(form.errors).join(' ') }}
            </p>

            <div class="flex items-center gap-3">
                <button type="submit" :disabled="form.processing" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50">
                    Ajouter
                </button>
                <button type="button" data-instrument-back class="text-sm text-muted-foreground" @click="back()">Retour</button>
            </div>
        </form>
    </div>
</template>
