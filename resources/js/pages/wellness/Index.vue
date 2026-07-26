<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { index as wellnessIndex } from '@/routes/wellness';

interface Point {
    date: string;
    sleep_hours: number | null;
    hrv: number | null;
    resting_hr: number | null;
    baseline_hrv: number | null;
    baseline_resting_hr: number | null;
    baseline_sleep: number | null;
}

const props = defineProps<{
    points: Point[];
    days: number;
    ranges: number[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Wellness', href: wellnessIndex() }],
    },
});

const rangeLabel: Record<number, string> = { 14: '2w', 42: '6w', 90: '3m' };

interface Series {
    key: keyof Point;
    baselineKey: keyof Point;
    label: string;
    unit: string;
    color: string;
}

const series: Series[] = [
    {
        key: 'sleep_hours',
        baselineKey: 'baseline_sleep',
        label: 'Sleep',
        unit: 'h',
        color: 'text-indigo-500',
    },
    {
        key: 'hrv',
        baselineKey: 'baseline_hrv',
        label: 'HRV',
        unit: 'ms',
        color: 'text-emerald-500',
    },
    {
        key: 'resting_hr',
        baselineKey: 'baseline_resting_hr',
        label: 'Resting HR',
        unit: 'bpm',
        color: 'text-rose-500',
    },
];

function values(key: keyof Point): (number | null)[] {
    return props.points.map((p) => p[key] as number | null);
}

function bounds(
    key: keyof Point,
    baselineKey: keyof Point,
): {
    min: number;
    max: number;
} {
    const all = [...values(key), ...values(baselineKey)].filter(
        (v): v is number => v !== null,
    );

    if (all.length === 0) {
        return { min: 0, max: 1 };
    }

    const min = Math.min(...all);
    const max = Math.max(...all);

    return {
        min: min === max ? min - 1 : min,
        max: min === max ? max + 1 : max,
    };
}

function polyline(
    vals: (number | null)[],
    b: { min: number; max: number },
): string {
    const n = vals.length;

    return vals
        .map((v, i) =>
            v === null
                ? null
                : `${((i / Math.max(1, n - 1)) * 100).toFixed(1)},${(100 - ((v - b.min) / (b.max - b.min)) * 100).toFixed(1)}`,
        )
        .filter((p): p is string => p !== null)
        .join(' ');
}

const charts = computed(() =>
    series.map((s) => {
        const b = bounds(s.key, s.baselineKey);
        const latest = [...values(s.key)].reverse().find((v) => v !== null) as
            number | undefined;
        const latestBaseline = [...values(s.baselineKey)]
            .reverse()
            .find((v) => v !== null) as number | undefined;

        return {
            ...s,
            line: polyline(values(s.key), b),
            baseline: polyline(values(s.baselineKey), b),
            latest: latest ?? null,
            latestBaseline: latestBaseline ?? null,
        };
    }),
);

const hasData = computed(() =>
    props.points.some(
        (p) =>
            p.sleep_hours !== null || p.hrv !== null || p.resting_hr !== null,
    ),
);
</script>

<template>
    <Head title="Wellness" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading
                title="Wellness"
                description="Sleep, HRV and resting HR over time"
            />
            <div class="flex rounded-lg border p-0.5 text-xs">
                <Link
                    v-for="r in ranges"
                    :key="r"
                    :href="`${wellnessIndex().url}?days=${r}`"
                    preserve-scroll
                    class="rounded-md px-2.5 py-1 font-medium"
                    :class="
                        days === r
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                >
                    {{ rangeLabel[r] ?? r }}
                </Link>
            </div>
        </div>

        <p
            v-if="!hasData"
            class="rounded-xl border bg-card p-10 text-center text-sm text-muted-foreground"
        >
            No wellness data in this range yet.
        </p>

        <div v-else class="grid gap-4 lg:grid-cols-3">
            <section
                v-for="chart in charts"
                :key="chart.key"
                class="rounded-xl border bg-card p-5"
            >
                <div class="mb-3 flex items-baseline justify-between">
                    <h2 class="text-sm font-bold">{{ chart.label }}</h2>
                    <span v-if="chart.latest !== null" class="text-sm">
                        <span class="font-semibold">{{ chart.latest }}</span>
                        <span class="text-muted-foreground">
                            {{ chart.unit }}</span
                        >
                    </span>
                </div>
                <svg
                    viewBox="0 0 100 100"
                    preserveAspectRatio="none"
                    class="h-32 w-full"
                >
                    <polyline
                        v-if="chart.baseline"
                        :points="chart.baseline"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1"
                        stroke-dasharray="3 2"
                        class="text-muted-foreground/60"
                        vector-effect="non-scaling-stroke"
                    />
                    <polyline
                        v-if="chart.line"
                        :points="chart.line"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        :class="chart.color"
                        vector-effect="non-scaling-stroke"
                    />
                </svg>
                <p class="mt-2 text-xs text-muted-foreground">
                    Dashed line is the 7-day baseline<span
                        v-if="chart.latestBaseline !== null"
                    >
                        ({{ chart.latestBaseline }} {{ chart.unit }})</span
                    >.
                </p>
            </section>
        </div>
    </div>
</template>
