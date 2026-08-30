<script setup lang="ts">
import { computed } from 'vue';
import HeroMetaList from '@/components/HeroMetaList.vue';
import { heroMeta, type HeroMetaEntry, type Instrument } from '@/lib/instrument';

const props = defineProps<{ instrument: Instrument }>();

const metaEntries = computed<HeroMetaEntry[]>(() => heroMeta(props.instrument));
</script>

<template>
    <section data-section="figures" class="px-6">
        <HeroMetaList :entries="metaEntries" />
    </section>
</template>
