<script setup lang="ts">
import { VisArea, VisAxis, VisLine, VisXYContainer } from '@unovis/vue';
import { computed } from 'vue';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartCrosshair,
    ChartTooltip,
    ChartTooltipContent,
    componentToString,
} from '@/components/ui/chart';

export interface AreaPoint {
    x: number;
    label: string;
    [series: string]: number | string;
}

const props = withDefaults(
    defineProps<{
        data: AreaPoint[];
        /** Series drawn as stacked bands, from the bottom of the stack upwards. */
        stackedKeys?: string[];
        /** Series drawn as standalone lines on top of the stack, such as income. */
        lineKeys?: string[];
        config: ChartConfig;
        height?: number;
    }>(),
    {
        stackedKeys: () => [],
        lineKeys: () => [],
        height: 180,
    },
);

/**
 * Build the rows Unovis needs to stack the bands.
 *
 * Each stacked series gets a hidden `__base<i>` / `__top<i>` pair holding its cumulative floor and
 * ceiling, which the `baseline` accessor of `VisArea` consumes. Keys prefixed with `__` are ignored
 * by the tooltip because they have no `config` entry, so only the meaningful series are displayed.
 */
const chartData = computed<AreaPoint[]>(() =>
    props.data.map((point) => {
        if (props.stackedKeys.length === 0) {
            return { ...point };
        }

        const row: AreaPoint = { ...point };
        let cumulative = 0;

        props.stackedKeys.forEach((key, index) => {
            row[`__base${index}`] = cumulative;
            cumulative += Number(point[key] ?? 0);
            row[`__top${index}`] = cumulative;
        });

        return row;
    }),
);

const tickValues = computed(() => props.data.map((point) => point.x));

function stackedTop(index: number) {
    return (point: AreaPoint) => Number(point[`__top${index}`] ?? 0);
}

function stackedBase(index: number) {
    return (point: AreaPoint) => Number(point[`__base${index}`] ?? 0);
}

function seriesValue(key: string) {
    return (point: AreaPoint) => Number(point[key] ?? 0);
}

function seriesColor(key: string) {
    return props.config[key]?.color ?? 'var(--chart-1)';
}

const tooltipTemplate = computed(() =>
    componentToString(props.config, ChartTooltipContent, {
        labelKey: 'label',
    }),
);
</script>

<template>
    <ChartContainer :config="config" class="aspect-auto">
        <VisXYContainer :data="chartData" :height="height">
            <template v-for="(key, index) in stackedKeys" :key="key">
                <VisArea
                    :x="(point: AreaPoint) => point.x"
                    :y="stackedTop(index)"
                    :baseline="stackedBase(index)"
                    :color="seriesColor(key)"
                    :opacity="0.5"
                />
            </template>

            <template v-for="key in lineKeys" :key="key">
                <VisLine
                    :x="(point: AreaPoint) => point.x"
                    :y="seriesValue(key)"
                    :color="seriesColor(key)"
                    :line-width="1.5"
                />
            </template>

            <template v-for="(key, index) in stackedKeys" :key="`line-${key}`">
                <VisLine
                    :x="(point: AreaPoint) => point.x"
                    :y="stackedTop(index)"
                    :color="seriesColor(key)"
                    :line-width="1.5"
                />
            </template>

            <VisAxis
                type="x"
                :x="(point: AreaPoint) => point.x"
                :tick-line="false"
                :domain-line="false"
                :grid-line="false"
                :tick-values="tickValues"
                :tick-format="
                    (value: number) => chartData[Math.round(value)]?.label ?? ''
                "
            />
            <VisAxis
                type="y"
                :num-ticks="3"
                :tick-line="false"
                :domain-line="false"
                :tick-format="(value: number) => String(Math.round(value))"
            />
            <ChartTooltip />
            <ChartCrosshair :template="tooltipTemplate" color="#0000" />
        </VisXYContainer>
    </ChartContainer>
</template>
