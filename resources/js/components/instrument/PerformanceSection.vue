<script setup lang="ts">
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import PerformanceBars from '@/components/PerformanceBars.vue';
import type { Performance } from '@/lib/performance';

defineProps<{ performances: Performance[] }>();
</script>

<template>
    <CollapsibleSection section="performance" title="Performances">
        <PerformanceBars :performances="performances" />
    </CollapsibleSection>
</template>
