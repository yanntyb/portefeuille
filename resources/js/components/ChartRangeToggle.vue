<script setup lang="ts" generic="T extends string">
const props = defineProps<{
    options: { key: T; label: string }[];
    modelValue: T;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: T] }>();

const select = (key: T): void => {
    if (props.modelValue === key) {
        return;
    }

    emit('update:modelValue', key);
};
</script>

<template>
    <div class="inline-flex rounded-md border border-border p-0.5">
        <button
            v-for="option in options"
            :key="option.key"
            type="button"
            class="rounded px-3 py-1 text-sm transition-colors"
            :class="modelValue === option.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
            @click="select(option.key)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
