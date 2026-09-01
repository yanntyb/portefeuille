<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import InstrumentSearchPanel, { type CreatedInstrument } from '@/components/instruments/InstrumentSearchPanel.vue';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { FormField } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect, type SelectOption } from '@/components/ui/native-select';
import { SearchSelect } from '@/components/ui/search-select';
import SegmentedControl, { type Segment } from '@/components/ui/SegmentedControl.vue';
import { eur, frDate, frQuantity } from '@/lib/format';
import { refreshableKeys } from '@/lib/inertiaRefresh';
import {
    cashDeltaOf,
    isAssetType,
    isTradeType,
    parseDecimalInput,
    payloadOf,
    transactionTotal,
    type TransactionDraft,
} from '@/lib/transactionForm';
import { useNetworkStore } from '@/stores/network';
import { useSnapshotStore } from '@/stores/snapshot';
import { useTransactionDialogStore } from '@/stores/transactionDialog';

/** Ce que sert `/transactions/options`. */
type FormOptions = {
    wallets: {
        id: number;
        name: string;
        broker: string | null;
        accountType: string;
        accountTypeLabel: string;
        cashBalance: number;
    }[];
    instruments: { id: number; name: string; ticker: string | null; lastPrice: number | null }[];
    held: { walletId: number; assetId: number; quantity: number }[];
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

/**
 * La ligne telle qu'elle était à l'ouverture. `form` bouge au fil de la frappe, et le stock
 * disponible d'une correction se lit contre la ligne d'origine, pas contre la saisie en cours.
 */
const opened: TransactionDraft = { ...dialog.draft };

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
        prefillUnitPrice();
    } catch {
        optionsFailed.value = true;
    }
});

/**
 * Le panneau remplace les champs, il ne s'ouvre pas par-dessus : un second `Dialog` sous celui de
 * `TransactionDialog` donnerait deux verrous de défilement et un `Échap` ambigu. Le formulaire
 * n'est pas démonté, seulement masqué — la saisie en cours survit.
 */
const searchingInstrument: Ref<boolean> = ref(false);

/** Les options rechargées de la même route qu'à l'ouverture : c'est elle qui porte le catalogue. */
const reloadOptions = async (): Promise<void> => {
    try {
        const response = await fetch('/transactions/options', { headers: { Accept: 'application/json' } });

        if (response.ok) {
            options.value = (await response.json()) as FormOptions;
        }
    } catch {
        optionsFailed.value = true;
    }
};

const onInstrumentCreated = async (instrument: CreatedInstrument): Promise<void> => {
    searchingInstrument.value = false;

    await reloadOptions();

    form.assetId = String(instrument.id);
    prefillUnitPrice();
};

/**
 * Établissement d'abord, régime ensuite — « IBKR - CTO » : c'est l'établissement qui situe le
 * compte, le régime le qualifie. Sans établissement, le nom du compte prend sa place, comme sur
 * les cartes d'enveloppes.
 */
/**
 * Une vente ne porte que sur un couple réellement détenu : les deux listes se restreignent l'une
 * l'autre à ce que la projection connaît. L'achat, lui, reste libre — c'est par lui qu'une position
 * s'ouvre, et filtrer y rendrait toute première acquisition impossible.
 */
const restrictedToHeld: ComputedRef<boolean> = computed((): boolean => form.type === 'sell');

const holds = (walletId: string, assetId: string): boolean =>
    (options.value?.held ?? []).some(
        (stock): boolean => String(stock.walletId) === walletId && String(stock.assetId) === assetId,
    );

const walletOptions: ComputedRef<SelectOption[]> = computed((): SelectOption[] =>
    (options.value?.wallets ?? [])
        /**
         * La valeur choisie reste offerte : retirée de la liste, un `<select>` n'afficherait rien —
         * et c'est aussi ce qui garde ses deux champs à la correction d'une vente soldante, que
         * `ProjectHolding` a effacée de la projection en ramenant la quantité à zéro.
         */
        .filter((wallet): boolean =>
            !restrictedToHeld.value || form.assetId === '' || String(wallet.id) === form.walletId
            || holds(String(wallet.id), form.assetId))
        .map((wallet): SelectOption => ({
            value: String(wallet.id),
            label: `${wallet.broker ?? wallet.name} - ${wallet.accountTypeLabel}`,
        })),
);

const instrumentOptions: ComputedRef<SelectOption[]> = computed((): SelectOption[] =>
    (options.value?.instruments ?? [])
        .filter((instrument): boolean =>
            !restrictedToHeld.value || form.walletId === '' || String(instrument.id) === form.assetId
            || holds(form.walletId, String(instrument.id)))
        .map((instrument): SelectOption => ({
            value: String(instrument.id),
            label: instrument.ticker === null ? instrument.name : `${instrument.name} · ${instrument.ticker}`,
        })),
);

/** Ce que dit un sélecteur vidé par le filtre : sans un mot, la liste semblerait ne pas s'être chargée. */
const walletPlaceholder: ComputedRef<string> = computed((): string =>
    restrictedToHeld.value && walletOptions.value.length === 0
        ? 'Aucune enveloppe ne détient cet actif'
        : 'Choisir une enveloppe',
);

const instrumentEmpty: ComputedRef<string> = computed((): string =>
    restrictedToHeld.value && form.walletId !== ''
        ? 'Aucun titre détenu dans cette enveloppe'
        : 'Aucun instrument',
);

/**
 * Le repli tant que le serveur n'a pas répondu. « Dividende » n'y est pas, comme il n'est pas dans
 * `TransactionFormOptionsData` : un dividende ne se saisit qu'en validant son détachement, seul
 * chemin qui connaisse l'ex-date, l'enveloppe détentrice et le garde anti-doublon.
 */
const typeOptions = [
    { value: 'buy', label: 'Achat' },
    { value: 'sell', label: 'Vente' },
    { value: 'deposit', label: 'Versement' },
    { value: 'withdrawal', label: 'Retrait' },
];

/**
 * La pastille « Dividende » ne revient que pour corriger une ligne déjà encaissée : elle existe,
 * elle reste modifiable et supprimable, et sans son segment le contrôle n'afficherait aucun type
 * sélectionné.
 */
const typeSegments: ComputedRef<Segment[]> = computed((): Segment[] => {
    const offered = (options.value?.types ?? typeOptions).map((type): Segment => ({ value: type.value, label: type.label }));

    if (form.type !== 'dividend' || offered.some((segment): boolean => segment.value === 'dividend')) {
        return offered;
    }

    return [...offered, { value: 'dividend', label: 'Dividende' }];
});

/** Le libellé français du type saisi, pour le volet de confirmation d'une suppression. */
const typeLabelOf = (type: string): string =>
    typeSegments.value.find((segment): boolean => segment.value === type)?.label ?? type;

const isEditing: ComputedRef<boolean> = computed((): boolean => dialog.editingId !== null);

/**
 * Un ordre échange une quantité d'actif contre un prix ; les trois autres types portent un montant
 * saisi directement. Un dividende garde tout de même son actif — il expose au marché — mais pas de
 * quantité ni de prix, ce n'est pas un échange.
 */
const isTrade: ComputedRef<boolean> = computed((): boolean => isTradeType(form.type));

/** Versement et retrait n'ont pas d'actif ; les trois autres types en portent un. */
const assetApplicable: ComputedRef<boolean> = computed((): boolean => isAssetType(form.type));

/** Le total vivant : le contrôle de cohérence le plus utile pendant une première saisie d'ordre. */
const total: ComputedRef<number | null> = computed((): number | null => transactionTotal(form.data()));

const blocked: ComputedRef<boolean> = computed((): boolean => !network.isOnline || form.processing);

/**
 * Ce que l'enveloppe choisie détient de l'actif choisi, et `null` tant que l'un des deux manque —
 * il n'y a alors pas de plafond à annoncer, pas un plafond de zéro.
 *
 * La projection compte la ligne en cours de correction ; le serveur, lui, s'en abstrait. On la
 * remet donc : corriger une vente de 4 en 5 se compare à un stock qui n'a pas déjà retranché ces 4
 * titres — même raisonnement que l'`$ignoringTransactionId` de `GetPositionStock`.
 */
const heldQuantity: ComputedRef<number | null> = computed((): number | null => {
    if (form.walletId === '' || form.assetId === '') {
        return null;
    }

    const position = options.value?.held.find(
        (stock): boolean =>
            String(stock.walletId) === form.walletId && String(stock.assetId) === form.assetId,
    );

    const projected = position?.quantity ?? 0;

    if (!isEditing.value || opened.walletId !== form.walletId || opened.assetId !== form.assetId) {
        return projected;
    }

    const own = parseDecimalInput(opened.quantity) ?? 0;

    return opened.type === 'sell' ? projected + own : projected - own;
});

/**
 * Une vente ne peut porter que sur ce qu'on détient : `ProjectHolding` supprime la position dès que
 * la quantité tombe à zéro, si bien qu'une survente l'effacerait au lieu de la mettre en défaut. Le
 * serveur le refuse déjà — on le dit ici avant l'envoi, dans les mêmes mots.
 */
const oversold: ComputedRef<boolean> = computed((): boolean => {
    const wanted = parseDecimalInput(form.quantity);

    return form.type === 'sell' && wanted !== null && heldQuantity.value !== null
        && wanted > heldQuantity.value;
});

/** Le plafond, sous le champ, dès que l'enveloppe et l'actif sont connus et qu'on vend. */
const quantityHint: ComputedRef<string | undefined> = computed((): string | undefined =>
    form.type === 'sell' && heldQuantity.value !== null
        ? `Maximum : ${frQuantity(heldQuantity.value)} titre(s) détenu(s)`
        : undefined,
);

/** Le message du serveur d'abord : lui seul connaît l'état après une saisie concurrente. */
const quantityError: ComputedRef<string | null> = computed((): string | null => {
    if (form.errors.quantity) {
        return form.errors.quantity;
    }

    return oversold.value && heldQuantity.value !== null
        ? `Vous ne détenez que ${frQuantity(heldQuantity.value)} titre(s) dans cette enveloppe.`
        : null;
});

/**
 * Ce que l'enveloppe choisie porte en espèces, et `null` tant qu'aucune n'est choisie — il n'y a
 * alors pas de plafond à annoncer, pas un plafond de zéro.
 *
 * Le solde servi est celui d'aujourd'hui, ligne éditée comprise ; le serveur, lui, la retranche de
 * son propre solde. On la rend donc, tant que la correction reste dans son enveloppe d'origine :
 * porter un retrait de 300 à 500 se compare à une caisse qui n'a pas déjà sorti ces 300 €, comme
 * pour le `balanceOfEditedTransaction` de `TransactionRequest`.
 */
const cashBalance: ComputedRef<number | null> = computed((): number | null => {
    if (form.walletId === '') {
        return null;
    }

    const wallet = options.value?.wallets.find((candidate): boolean => String(candidate.id) === form.walletId);

    if (wallet === undefined) {
        return null;
    }

    if (!isEditing.value || opened.walletId !== form.walletId) {
        return wallet.cashBalance;
    }

    return wallet.cashBalance - cashDeltaOf(opened);
});

/**
 * On ne retire pas plus que la caisse ne porte : le solde d'une enveloppe n'est négatif à aucune
 * date, et un retrait à découvert ferait déduire un versement pour le combler — l'application
 * inventerait un apport que le porteur n'a pas fait. Le serveur le refuse déjà, à la date saisie ;
 * on le dit ici avant l'envoi, dans les mêmes mots.
 */
const overdrawn: ComputedRef<boolean> = computed((): boolean => {
    const wanted = parseDecimalInput(form.amount);

    return form.type === 'withdrawal' && wanted !== null && cashBalance.value !== null
        && wanted > cashBalance.value;
});

/** Le plafond, sous le champ, dès que l'enveloppe est connue et qu'on retire. */
const amountHint: ComputedRef<string | undefined> = computed((): string | undefined =>
    form.type === 'withdrawal' && cashBalance.value !== null
        ? `Maximum : ${eur(cashBalance.value)} en espèces`
        : 'En euros',
);

/** Le message du serveur d'abord : lui seul compte à la date saisie, et après une saisie concurrente. */
const amountError: ComputedRef<string | null> = computed((): string | null => {
    if (form.errors.amount) {
        return form.errors.amount;
    }

    return overdrawn.value && cashBalance.value !== null
        ? `Cette enveloppe ne détient que ${eur(cashBalance.value)} en espèces.`
        : null;
});

/** Ce que le volet de confirmation récapitule ; la date s'y lit en français, pas en ISO. */
const deletionLabel: ComputedRef<string> = computed(
    (): string => `${typeLabelOf(form.type)} du ${frDate(form.date)}`,
);

/**
 * Le cours connu pré-remplit le prix unitaire — la valeur la plus souvent juste pour un ordre saisi
 * le jour même — mais seulement sur un champ encore vide : on ne réécrit pas une saisie en cours,
 * et on ne touche jamais à une correction.
 *
 * Appelé au choix d'un actif **et** à l'arrivée du catalogue : ouverte depuis une fiche d'actif, la
 * modale a déjà son `assetId` et n'émettra jamais de changement, et même au choix libre le
 * catalogue peut n'être pas encore là.
 */
const prefillUnitPrice = (): void => {
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
    if (blocked.value || oversold.value || overdrawn.value) {
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
    <!--
        Le panneau prend la place des champs, il ne s'ouvre pas par-dessus : un second `Dialog` sous
        celui de `TransactionDialog` donnerait deux verrous de défilement et un `Échap` ambigu — la
        même raison que le volet de confirmation d'une suppression, plus bas, n'est pas une modale.
        Le composant reste monté, seul son gabarit change : la saisie en cours n'est pas perdue.
    -->
    <InstrumentSearchPanel
        v-if="searchingInstrument"
        @created="onInstrumentCreated"
        @cancel="searchingInstrument = false"
        @open="searchingInstrument = false"
    />

    <template v-else>
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

        <!-- En premier : l'enveloppe est le cadre de l'opération, tout le reste s'y inscrit. -->
        <FormField id="transaction-wallet" label="Enveloppe" :error="form.errors.walletId">
            <template #default="{ describedBy, invalid }">
                <NativeSelect
                    id="transaction-wallet"
                    v-model="form.walletId"
                    :options="walletOptions"
                    :placeholder="walletPlaceholder"
                    :invalid="invalid"
                    :disabled="blocked"
                    :aria-describedby="describedBy"
                />
            </template>
        </FormField>

        <!--
            Versement et retrait n'ont pas d'actif : le serveur l'interdit par une règle
            `prohibited`, le champ n'a donc rien à faire ici pour ces deux types.
        -->
        <template v-if="assetApplicable">
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

            <template v-else>
                <FormField id="transaction-asset" label="Actif" :error="form.errors.assetId">
                    <template #default="{ describedBy, invalid }">
                        <!--
                            Le seul champ cherchable du formulaire : le catalogue est la seule liste qui
                            grossit sans limite, et le ticker se tape plus vite qu'il ne se déroule.
                        -->
                        <SearchSelect
                            id="transaction-asset"
                            v-model="form.assetId"
                            :options="instrumentOptions"
                            placeholder="Choisir un actif"
                            :empty="instrumentEmpty"
                            :invalid="invalid"
                            :disabled="blocked"
                            :aria-describedby="describedBy"
                            @change="prefillUnitPrice()"
                        />
                    </template>
                </FormField>

                <!-- Absent quand l'actif est imposé par la page : en changer n'aurait pas de sens. -->
                <button
                    type="button"
                    data-transaction-add-instrument
                    class="self-start text-sm text-muted-foreground underline"
                    @click="searchingInstrument = true"
                >
                    L'actif n'est pas dans la liste ?
                </button>
            </template>
        </template>

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
        <template v-if="isTrade">
            <FormField
                id="transaction-quantity"
                label="Quantité"
                :hint="quantityHint"
                :error="quantityError"
            >
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
        </template>

        <!--
            Versement, retrait et dividende portent un montant saisi, sans quantité, prix ni frais :
            « montant seul » — le serveur les interdit d'ailleurs par une règle `prohibited`.
        -->
        <FormField v-else id="transaction-amount" label="Montant" :hint="amountHint" :error="amountError">
            <template #default="{ describedBy, invalid }">
                <Input
                    id="transaction-amount"
                    v-model="form.amount"
                    type="text"
                    inputmode="decimal"
                    autocomplete="off"
                    :disabled="blocked"
                    :aria-invalid="invalid || undefined"
                    :aria-describedby="describedBy"
                />
            </template>
        </FormField>

        <!-- Propre à un ordre : le montant d'un mouvement d'espèces est déjà ce qu'on vient de saisir. -->
        <p v-if="isTrade" class="flex items-baseline justify-between border-t border-border pt-3 text-sm">
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
                data-transaction-delete-edited
                class="text-destructive hover:text-destructive sm:mr-auto"
                :disabled="form.processing"
                @click="dialog.askDelete(deletionLabel)"
            >
                Supprimer
            </Button>

            <Button type="button" variant="outline" :disabled="form.processing" @click="dialog.close()">
                Annuler
            </Button>

            <!--
                Une vente au-delà du stock, un retrait au-delà de la caisse : le serveur les
                refuserait, et le dit déjà sous le champ.
            -->
            <Button type="submit" data-transaction-submit :disabled="blocked || oversold || overdrawn">
                {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
            </Button>
        </DialogFooter>
    </form>
    </template>
</template>
