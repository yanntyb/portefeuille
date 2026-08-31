<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { FormField } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect, type SelectOption } from '@/components/ui/native-select';
import SegmentedControl, { type Segment } from '@/components/ui/SegmentedControl.vue';
import { eur } from '@/lib/format';
import { refreshableKeys } from '@/lib/inertiaRefresh';
import { payloadOf, transactionTotal, type TransactionDraft } from '@/lib/transactionForm';
import { useNetworkStore } from '@/stores/network';
import { useSnapshotStore } from '@/stores/snapshot';
import { useTransactionDialogStore } from '@/stores/transactionDialog';

/** Ce que sert `/transactions/options`. */
type FormOptions = {
    wallets: { id: number; name: string; accountType: string; accountTypeLabel: string }[];
    instruments: { id: number; name: string; ticker: string | null; lastPrice: number | null }[];
    types: { value: string; label: string }[];
};

const dialog = useTransactionDialogStore();
const network = useNetworkStore();
const snapshot = useSnapshotStore();
const page = usePage();

/**
 * `useForm(data)` et non `useForm('put', url, data)` : la seconde forme rend un formulaire à
 * précognition, qui validerait auprès du serveur pendant la frappe — l'inverse de ce qu'on veut sur
 * un chemin dont la règle est « hors-ligne, on ne saisit pas ».
 *
 * Le composant est remonté par sa `key` à chaque ouverture : `useForm` fige ses valeurs initiales,
 * et sans remontage un formulaire d'édition garderait celles de la fois précédente.
 */
const form = useForm<TransactionDraft>({ ...dialog.draft });

const options: Ref<FormOptions | null> = ref(null);
const optionsFailed: Ref<boolean> = ref(false);

/**
 * Chargées à l'ouverture et non servies en prop : la modale vit sur trois pages, et ces listes ne
 * concernent que qui saisit. Le service worker les laisse passer sans cache — un catalogue périmé
 * ferait saisir contre des instruments qu'une synchronisation vient d'ajouter.
 */
onMounted(async (): Promise<void> => {
    if (!network.isOnline) {
        return;
    }

    try {
        const response = await fetch('/transactions/options', {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            optionsFailed.value = true;

            return;
        }

        options.value = (await response.json()) as FormOptions;
    } catch {
        optionsFailed.value = true;
    }
});

const walletOptions: ComputedRef<SelectOption[]> = computed((): SelectOption[] =>
    (options.value?.wallets ?? []).map((wallet): SelectOption => ({
        value: String(wallet.id),
        label: `${wallet.name} · ${wallet.accountTypeLabel}`,
    })),
);

const instrumentOptions: ComputedRef<SelectOption[]> = computed((): SelectOption[] =>
    (options.value?.instruments ?? []).map((instrument): SelectOption => ({
        value: String(instrument.id),
        label: instrument.ticker === null ? instrument.name : `${instrument.name} · ${instrument.ticker}`,
    })),
);

const typeSegments: ComputedRef<Segment[]> = computed((): Segment[] =>
    (options.value?.types ?? [
        { value: 'buy', label: 'Achat' },
        { value: 'sell', label: 'Vente' },
    ]).map((type): Segment => ({ value: type.value, label: type.label })),
);

const isEditing: ComputedRef<boolean> = computed((): boolean => dialog.editingId !== null);

/** Le total vivant : le contrôle de cohérence le plus utile pendant une première saisie. */
const total: ComputedRef<number | null> = computed((): number | null => transactionTotal(form.data()));

const blocked: ComputedRef<boolean> = computed((): boolean => !network.isOnline || form.processing);

/**
 * Le cours connu pré-remplit le prix unitaire — la valeur la plus souvent juste pour un ordre saisi
 * le jour même — mais seulement sur un champ encore vide : on ne réécrit pas une saisie en cours,
 * et on ne touche jamais à une correction.
 */
const onInstrumentChange = (): void => {
    if (isEditing.value || form.unitPrice !== '') {
        return;
    }

    const chosen = options.value?.instruments.find(
        (instrument): boolean => String(instrument.id) === form.assetId,
    );

    if (chosen?.lastPrice != null) {
        form.unitPrice = String(chosen.lastPrice);
    }
};

const submit = (): void => {
    if (blocked.value) {
        return;
    }

    const shared = {
        /**
         * Les props déjà chargées, et elles seules : celles qu'on ne nomme pas gardent leur ancienne
         * valeur, celles qu'on inventerait feraient calculer au serveur une section repliée.
         */
        only: refreshableKeys(page.props),
        /**
         * Sans quoi la page se remonte : toutes les sections se replient, toutes les années
         * dépliées se referment, et le lecteur perd de vue la ligne qu'il vient de saisir.
         */
        preserveState: true,
        preserveScroll: true,
        onSuccess: (): void => {
            dialog.close();
            /** Sinon le blob hors-ligne garde l'état d'avant et clignoterait à la prochaine visite. */
            void snapshot.sync();
        },
        onNetworkError: (): void => {
            serverUnreachable.value = true;
        },
    };

    form.transform(payloadOf);

    if (dialog.editingId === null) {
        form.post('/transactions', shared);

        return;
    }

    form.put(`/transactions/${dialog.editingId}`, shared);
};

/**
 * `navigator.onLine` à vrai ne prouve pas que le serveur répond — portail captif, Herd éteint. Le
 * message reste, et la saisie avec lui : le lecteur n'a rien à retaper.
 */
const serverUnreachable: Ref<boolean> = ref(false);
</script>

<template>
    <form class="flex flex-col gap-4" @submit.prevent="submit()">
        <p v-if="!network.isOnline" data-offline-notice class="text-sm text-muted-foreground">
            Hors-ligne : la saisie est indisponible.
        </p>

        <p v-else-if="serverUnreachable" data-form-error role="alert" class="text-sm text-destructive">
            Enregistrement impossible : le serveur est injoignable. Votre saisie est conservée.
        </p>

        <p v-else-if="optionsFailed" data-form-error role="alert" class="text-sm text-destructive">
            Les enveloppes et les instruments n'ont pas pu être chargés.
        </p>

        <!--
            Instrument imposé par la page : sur une fiche d'actif, un sélecteur modifiable
            laisserait enregistrer une opération qui n'apparaîtrait pas sur la page qu'on regarde.
        -->
        <div v-if="dialog.lockedAssetName !== null" class="flex flex-col gap-1.5">
            <span class="text-sm leading-none font-medium">Actif</span>
            <p data-transaction-asset-locked class="text-sm text-muted-foreground">
                {{ dialog.lockedAssetName }}
            </p>
        </div>

        <FormField v-else id="transaction-asset" label="Actif" :error="form.errors.assetId">
            <template #default="{ describedBy, invalid }">
                <NativeSelect
                    id="transaction-asset"
                    v-model="form.assetId"
                    :options="instrumentOptions"
                    placeholder="Choisir un actif"
                    :invalid="invalid"
                    :disabled="blocked"
                    :aria-describedby="describedBy"
                    @change="onInstrumentChange()"
                />
            </template>
        </FormField>

        <div class="flex flex-col gap-1.5">
            <span class="text-sm leading-none font-medium">Sens</span>
            <SegmentedControl
                v-model="form.type"
                variant="radio"
                label="Sens de l'opération"
                :segments="typeSegments"
                class="self-start"
            />
            <p v-if="form.errors.type" data-field-error class="text-xs text-destructive">
                {{ form.errors.type }}
            </p>
        </div>

        <FormField id="transaction-date" label="Date" :error="form.errors.date">
            <template #default="{ describedBy, invalid }">
                <Input
                    id="transaction-date"
                    v-model="form.date"
                    type="date"
                    :disabled="blocked"
                    :aria-invalid="invalid || undefined"
                    :aria-describedby="describedBy"
                />
            </template>
        </FormField>

        <!--
            `type="text"` et non `type="number"` : sur un clavier français la touche décimale produit
            une virgule, qui rend un champ numérique invalide et vide sa valeur sans un mot.
            `inputmode="decimal"` appelle quand même le pavé numérique sur mobile.
        -->
        <FormField id="transaction-quantity" label="Quantité" :error="form.errors.quantity">
            <template #default="{ describedBy, invalid }">
                <Input
                    id="transaction-quantity"
                    v-model="form.quantity"
                    type="text"
                    inputmode="decimal"
                    autocomplete="off"
                    :disabled="blocked"
                    :aria-invalid="invalid || undefined"
                    :aria-describedby="describedBy"
                />
            </template>
        </FormField>

        <FormField
            id="transaction-unit-price"
            label="Prix unitaire"
            hint="En euros"
            :error="form.errors.unitPrice"
        >
            <template #default="{ describedBy, invalid }">
                <Input
                    id="transaction-unit-price"
                    v-model="form.unitPrice"
                    type="text"
                    inputmode="decimal"
                    autocomplete="off"
                    :disabled="blocked"
                    :aria-invalid="invalid || undefined"
                    :aria-describedby="describedBy"
                />
            </template>
        </FormField>

        <FormField id="transaction-fees" label="Frais" hint="En euros" :error="form.errors.fees">
            <template #default="{ describedBy, invalid }">
                <Input
                    id="transaction-fees"
                    v-model="form.fees"
                    type="text"
                    inputmode="decimal"
                    autocomplete="off"
                    :disabled="blocked"
                    :aria-invalid="invalid || undefined"
                    :aria-describedby="describedBy"
                />
            </template>
        </FormField>

        <!-- En dernier : c'est le champ qui change le moins souvent d'une saisie à l'autre. -->
        <FormField id="transaction-wallet" label="Enveloppe" :error="form.errors.walletId">
            <template #default="{ describedBy, invalid }">
                <NativeSelect
                    id="transaction-wallet"
                    v-model="form.walletId"
                    :options="walletOptions"
                    placeholder="Choisir une enveloppe"
                    :invalid="invalid"
                    :disabled="blocked"
                    :aria-describedby="describedBy"
                />
            </template>
        </FormField>

        <p class="flex items-baseline justify-between border-t border-border pt-3 text-sm">
            <span class="text-muted-foreground">
                {{ form.type === 'sell' ? 'Montant perçu' : 'Montant investi' }}
            </span>
            <span data-transaction-total class="font-semibold">{{ eur(total) }}</span>
        </p>

        <DialogFooter>
            <Button
                v-if="isEditing"
                type="button"
                variant="ghost"
                data-transaction-delete
                class="text-destructive hover:text-destructive sm:mr-auto"
                :disabled="form.processing"
                @click="dialog.askDelete(`${form.type === 'sell' ? 'Vente' : 'Achat'} du ${form.date}`)"
            >
                Supprimer
            </Button>

            <Button type="button" variant="outline" :disabled="form.processing" @click="dialog.close()">
                Annuler
            </Button>

            <Button type="submit" data-transaction-submit :disabled="blocked">
                {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
            </Button>
        </DialogFooter>
    </form>
</template>
