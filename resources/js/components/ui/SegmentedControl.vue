<script setup lang="ts">
export type Segment = {
    value: string;
    label: string;
    /** Un segment sans données reste visible mais inerte : le masquer ferait bouger la commande. */
    disabled?: boolean;
};

const props = defineProps<{ modelValue: string; segments: Segment[]; label: string }>();

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const select = (segment: Segment): void => {
    if (!segment.disabled) {
        emit('update:modelValue', segment.value);
    }
};

/**
 * Les flèches parcourent la commande comme un jeu d'onglets : le pas boucle d'un bout à l'autre,
 * et un segment inerte se saute plutôt que de retenir le curseur.
 */
const step = (direction: number): void => {
    const from = props.segments.findIndex((segment: Segment): boolean => segment.value === props.modelValue);
    const next = props.segments.at((from + direction) % props.segments.length);

    if (next !== undefined) {
        select(next);
    }
};

const onArrow = (event: KeyboardEvent): void => {
    if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
        return;
    }

    event.preventDefault();
    step(event.key === 'ArrowRight' ? 1 : -1);
};
</script>

<template>
    <div
        role="tablist"
        :aria-label="props.label"
        class="inline-flex gap-0.5 rounded-full bg-muted p-0.5 text-sm"
        @keydown="onArrow"
    >
        <button
            v-for="segment in props.segments"
            :key="segment.value"
            type="button"
            role="tab"
            :data-segment="segment.value"
            :aria-selected="segment.value === props.modelValue"
            :disabled="segment.disabled"
            :tabindex="segment.value === props.modelValue ? 0 : -1"
            class="rounded-full px-3 py-1 leading-none font-medium transition-colors disabled:opacity-40"
            :class="segment.value === props.modelValue
                ? 'bg-background text-foreground shadow-sm'
                : 'text-muted-foreground'"
            @click="select(segment)"
        >
            {{ segment.label }}
        </button>
    </div>
</template>
