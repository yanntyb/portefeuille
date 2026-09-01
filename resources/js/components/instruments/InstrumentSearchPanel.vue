<script setup lang="ts">
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
const submitting: Ref<boolean> = ref(false);
const errors: Ref<Record<string, string>> = ref({});

const draft = ref({ name: '', ticker: '', isin: '', type: 'stock', assetClass: 'equity' });

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
    errors.value = {};
    draft.value = {
        name: result.name,
        ticker: result.symbol,
        isin: '',
        type,
        /** L'exposition de la page prime : un instrument créé ailleurs disparaîtrait au retour. */
        assetClass: props.exposure ?? defaultClassFor(type),
    };
};

const back = (): void => {
    chosen.value = null;
    errors.value = {};
};

const submit = async (): Promise<void> => {
    if (submitting.value) {
        return;
    }

    submitting.value = true;
    errors.value = {};

    try {
        const response = await fetch('/instruments', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                name: draft.value.name,
                ticker: draft.value.ticker,
                isin: draft.value.isin === '' ? null : draft.value.isin,
                type: draft.value.type,
                assetClass: draft.value.assetClass,
            }),
        });

        if (response.status === 422) {
            const body = (await response.json()) as { errors?: Record<string, string[]> };

            errors.value = Object.fromEntries(
                Object.entries(body.errors ?? {}).map(([field, messages]): [string, string] => [field, messages[0]]),
            );

            return;
        }

        if (!response.ok) {
            errors.value = { global: "L'instrument n'a pas pu être créé." };

            return;
        }

        emit('created', (await response.json()) as CreatedInstrument);
    } catch {
        errors.value = { global: "L'instrument n'a pas pu être créé." };
    } finally {
        submitting.value = false;
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
                <li v-for="result in results" :key="result.symbol" data-instrument-result class="border-b border-separator last:border-b-0">
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
                <Input v-model="draft.name" data-instrument-name type="text" />
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-medium">
                Ticker
                <Input v-model="draft.ticker" data-instrument-ticker type="text" />
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-medium">
                ISIN
                <Input v-model="draft.isin" data-instrument-isin type="text" />
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-medium">
                Type
                <select v-model="draft.type" data-instrument-type class="rounded-md border border-input bg-background px-3 py-2 font-normal">
                    <option v-for="option in TYPES" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-medium">
                Exposition
                <select v-model="draft.assetClass" data-instrument-asset-class class="rounded-md border border-input bg-background px-3 py-2 font-normal">
                    <option v-for="option in ASSET_CLASSES" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </label>

            <p v-if="Object.keys(errors).length" data-instrument-error class="text-xs text-destructive">
                {{ Object.values(errors).join(' ') }}
            </p>

            <div class="flex items-center gap-3">
                <button type="submit" :disabled="submitting" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50">
                    Ajouter
                </button>
                <button type="button" data-instrument-back class="text-sm text-muted-foreground" @click="back()">Retour</button>
            </div>
        </form>
    </div>
</template>
