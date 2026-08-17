<script setup lang="ts">
const props = defineProps<{
    series: string;
    label: string;
    color: string;
    modelValue: boolean;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();

const toggle = (): void => {
    emit('update:modelValue', !props.modelValue);
};
</script>

<template>
    <button
        type="button"
        :data-series-toggle="series"
        :aria-pressed="modelValue"
        class="inline-flex items-center gap-2 rounded-md border border-border px-3 py-1 text-sm transition-colors"
        :class="modelValue ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
        @click="toggle"
    >
        <span
            class="size-2 rounded-full transition-opacity"
            :class="modelValue ? 'opacity-100' : 'opacity-30'"
            :style="{ backgroundColor: color }"
        ></span>
        {{ label }}
    </button>
</template>
