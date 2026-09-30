<script setup lang="ts">
import { Orientation } from '@unovis/ts';
import { VisAxis, VisGroupedBar, VisXYContainer } from '@unovis/vue';
import { computed } from 'vue';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartCrosshair,
    ChartTooltip,
    ChartTooltipContent,
    componentToString,
} from '@/components/ui/chart';

export interface HBarPoint {
    x: number;
    label: string;
    [seriesKey: string]: number | string;
}

const props = withDefaults(
    defineProps<{
        data: HBarPoint[];
        config: ChartConfig;
        seriesKey: string;
        height?: number;
    }>(),
    {
        height: 200,
    },
);

const tickValues = computed(() => props.data.map((point) => point.x));

function seriesValue(point: HBarPoint): number {
    return Number(point[props.seriesKey]) || 0;
}

const seriesColor = computed(
    () => props.config[props.seriesKey]?.color ?? 'var(--chart-1)',
);

const tooltipTemplate = computed(() =>
    componentToString(props.config, ChartTooltipContent, {
        hideLabel: true,
    }),
);
</script>

<template>
    <ChartContainer :config="config" class="aspect-auto">
        <VisXYContainer :data="data" :height="height">
            <VisGroupedBar
                :x="(point: HBarPoint) => point.x"
                :y="seriesValue"
                :color="seriesColor"
                :rounded-corners="5"
                :orientation="Orientation.Horizontal"
            />
            <VisAxis
                type="y"
                :x="(point: HBarPoint) => point.x"
                :tick-line="false"
                :domain-line="false"
                :grid-line="false"
                :tick-values="tickValues"
                :tick-format="
                    (value: number) =>
                        data[Math.round(value)]?.label ?? ''
                "
            />
            <ChartTooltip />
            <ChartCrosshair :template="tooltipTemplate" color="#0000" />
        </VisXYContainer>
    </ChartContainer>
</template>
