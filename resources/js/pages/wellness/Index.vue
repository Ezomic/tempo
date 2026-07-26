<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import {
    store as lifeEventsStore,
    destroy as lifeEventsDestroy,
} from '@/routes/life-events';
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

interface LifeEvent {
    id: number;
    date: string;
    kind: string;
    note: string | null;
}

const props = defineProps<{
    points: Point[];
    days: number;
    ranges: number[];
    lifeEvents: LifeEvent[];
    kinds: string[];
}>();

const eventForm = useForm({ date: '', kind: 'travel', note: '' });

function addEvent(): void {
    eventForm.post(lifeEventsStore().url, {
        preserveScroll: true,
        onSuccess: () => eventForm.reset('note'),
    });
}

function removeEvent(id: number): void {
    router.delete(lifeEventsDestroy(id).url, { preserveScroll: true });
}

function kindLabel(kind: string): string {
    return kind.replace('_', ' ');
}

// x position (0-100) of a date within the current point range.
const eventMarkers = computed(() => {
    const dates = props.points.map((p) => p.date);
    const n = dates.length;

    return props.lifeEvents
        .map((e) => {
            const i = dates.indexOf(e.date);

            return i === -1
                ? null
                : { id: e.id, x: (i / Math.max(1, n - 1)) * 100, kind: e.kind };
        })
        .filter(
            (m): m is { id: number; x: number; kind: string } => m !== null,
        );
});

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
                    <line
                        v-for="marker in eventMarkers"
                        :key="marker.id"
                        :x1="marker.x"
                        :x2="marker.x"
                        y1="0"
                        y2="100"
                        stroke="currentColor"
                        stroke-width="1"
                        class="text-amber-500/50"
                        vector-effect="non-scaling-stroke"
                    />
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

        <!-- Life events -->
        <section class="rounded-xl border bg-card p-5">
            <div class="mb-4">
                <h2 class="text-sm font-bold">Life events</h2>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Annotate days with non-training context. Amber markers on
                    the charts above show where they fall.
                </p>
            </div>

            <form
                class="flex flex-wrap items-end gap-2"
                @submit.prevent="addEvent"
            >
                <input
                    v-model="eventForm.date"
                    type="date"
                    class="rounded-md border bg-background px-3 py-2 text-sm"
                />
                <select
                    v-model="eventForm.kind"
                    class="rounded-md border bg-background px-3 py-2 text-sm capitalize"
                >
                    <option v-for="k in kinds" :key="k" :value="k">
                        {{ kindLabel(k) }}
                    </option>
                </select>
                <input
                    v-model="eventForm.note"
                    type="text"
                    placeholder="Note (optional)"
                    class="min-w-40 flex-1 rounded-md border bg-background px-3 py-2 text-sm"
                />
                <button
                    type="submit"
                    :disabled="eventForm.processing"
                    class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                >
                    Add
                </button>
            </form>
            <p v-if="eventForm.errors.date" class="mt-1 text-xs text-red-500">
                {{ eventForm.errors.date }}
            </p>

            <ul v-if="lifeEvents.length" class="mt-4 space-y-1.5">
                <li
                    v-for="event in lifeEvents"
                    :key="event.id"
                    class="flex items-center justify-between gap-3 rounded-lg border bg-background px-3 py-2 text-sm"
                >
                    <div>
                        <span class="font-medium capitalize">{{
                            kindLabel(event.kind)
                        }}</span>
                        <span class="text-muted-foreground">
                            · {{ event.date
                            }}<span v-if="event.note">
                                · {{ event.note }}</span
                            ></span
                        >
                    </div>
                    <button
                        class="text-xs text-muted-foreground hover:text-red-500"
                        @click="removeEvent(event.id)"
                    >
                        Remove
                    </button>
                </li>
            </ul>
        </section>
    </div>
</template>
