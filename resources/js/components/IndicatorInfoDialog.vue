<script setup lang="ts">
import { computed } from 'vue';
import { Info } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { IndicatorId } from '@/lib/instrumentAnalysis';
import { indicatorHelp } from '@/lib/indicatorHelp';

const props = defineProps<{ indicator: IndicatorId }>();

const help = computed(() => indicatorHelp[props.indicator]);
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button
                variant="ghost"
                size="icon-sm"
                class="text-muted-foreground hover:text-foreground"
                :aria-label="help.title"
            >
                <Info />
            </Button>
        </DialogTrigger>

        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ help.title }}</DialogTitle>
                <DialogDescription>{{ help.subtitle }}</DialogDescription>
            </DialogHeader>

            <div class="flex flex-col gap-3 text-sm text-foreground">
                <p v-for="paragraph in help.body" :key="paragraph">{{ paragraph }}</p>

                <p v-if="help.formula" class="rounded-md bg-muted px-3 py-2 font-mono text-xs">
                    {{ help.formula }}
                </p>

                <p v-if="help.caveat" class="text-muted-foreground">{{ help.caveat }}</p>
            </div>
        </DialogContent>
    </Dialog>
</template>
