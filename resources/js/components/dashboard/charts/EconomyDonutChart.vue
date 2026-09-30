<script setup lang="ts">
import { Donut } from '@unovis/ts';
import { VisDonut, VisSingleContainer } from '@unovis/vue';
import { computed } from 'vue';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    componentToString,
} from '@/components/ui/chart';

export interface DonutSlice {
    key: string;
    label: string;
    value: number;
}

const props = withDefaults(
    defineProps<{
        slices: DonutSlice[];
        config: ChartConfig;
        centralLabel?: string;
        centralSubLabel?: string;
        height?: number;
    }>(),
    {
        centralLabel: undefined,
        centralSubLabel: undefined,
        height: 220,
    },
);

/**
 * Unovis segment payloads must carry the value under a config-matching key,
 * plus a `fill` CSS variable, mirroring the shadcn donut registry demos.
 */
const donutData = computed(() =>
    props.slices.map((slice) => ({
        [slice.key]: slice.value,
        fill: `var(--color-${slice.key})`,
    })),
);

function datumKey(datum: Record<string, unknown>): string {
    return (
        Object.keys(datum).find((key) => key !== 'fill') ?? ''
    );
}

const tooltipTemplate = computed(() =>
    componentToString(props.config, ChartTooltipContent, {
        hideLabel: true,
    }),
);
</script>

<template>
    <ChartContainer
        :config="config"
        class="mx-auto aspect-square max-h-[240px]"
        :style="{
            '--vis-donut-central-label-font-size': 'var(--text-xl)',
            '--vis-donut-central-label-font-weight': 'var(--font-weight-semibold)',
            '--vis-donut-central-label-text-color': 'var(--foreground)',
            '--vis-donut-central-sub-label-text-color': 'var(--muted-foreground)',
        }"
    >
        <VisSingleContainer
            :data="donutData"
            :height="height"
            :margin="{ top: 20, bottom: 20 }"
        >
            <VisDonut
                :value="(d: Record<string, unknown>) => Number(d[datumKey(d)])"
                :color="
                    (d: Record<string, unknown>) =>
                        config[datumKey(d)]?.color ?? 'var(--chart-1)'
                "
                :arc-width="26"
                :central-label="centralLabel"
                :central-sub-label="centralSubLabel"
                :pad-angle="8"
            />
            <ChartTooltip
                :triggers="{
                    [Donut.selectors.segment]: tooltipTemplate,
                }"
            />
        </VisSingleContainer>
    </ChartContainer>
</template>
