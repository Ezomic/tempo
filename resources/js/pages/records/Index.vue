<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { show } from '@/routes/activities';
import { all as exportAll } from '@/routes/export';
import {
    enable as shareEnable,
    disable as shareDisable,
    show as publicProfile,
} from '@/routes/public-profile';
import { index as recordsIndex } from '@/routes/records';

interface RecordRow {
    distance_m: number;
    distance_label: string;
    duration_s: number;
    time: string;
    pace: string;
    achieved_on: string;
    activity_id: number;
    is_recent: boolean;
}

interface MeanMaxRow {
    duration_s: number;
    duration_label: string;
    speed_mps: number;
    pace: string;
}

interface Vo2Point {
    week_start: string;
    value: number;
}

interface ThresholdPoint {
    week_start: string;
    speed_mps: number;
    pace_s_per_km: number;
}

const props = defineProps<{
    records: RecordRow[];
    meanMax: MeanMaxRow[];
    sport: string;
    availableSports: string[];
    fitnessMarkers: { vo2max: Vo2Point[]; threshold: ThresholdPoint[] };
    shareToken: string | null;
}>();

const shareUrl = computed(() =>
    props.shareToken
        ? new URL(publicProfile(props.shareToken).url, window.location.origin)
              .href
        : null,
);

function enableShare(): void {
    router.post(shareEnable().url, {}, { preserveScroll: true });
}

function disableShare(): void {
    router.delete(shareDisable().url, { preserveScroll: true });
}

const hasMarkers = computed(
    () =>
        props.fitnessMarkers.vo2max.length >= 2 ||
        props.fitnessMarkers.threshold.length >= 2,
);

function normalize(values: number[]): number[] {
    const min = Math.min(...values);
    const max = Math.max(...values);
    const span = max - min || 1;

    return values.map((v) => 100 - ((v - min) / span) * 100);
}

function markerSpark(ys: number[]): string {
    if (ys.length < 2) {
        return '';
    }

    return ys
        .map(
            (y, i) =>
                `${((i / (ys.length - 1)) * 100).toFixed(1)},${(y * 0.4).toFixed(1)}`,
        )
        .join(' ');
}

const vo2Spark = computed(() =>
    markerSpark(normalize(props.fitnessMarkers.vo2max.map((p) => p.value))),
);

const thresholdSpark = computed(() =>
    markerSpark(
        normalize(props.fitnessMarkers.threshold.map((p) => p.speed_mps)),
    ),
);

function thresholdPace(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = Math.round(seconds % 60);

    return `${m}:${String(s).padStart(2, '0')}/km`;
}

const latestVo2 = computed(
    () =>
        props.fitnessMarkers.vo2max[props.fitnessMarkers.vo2max.length - 1] ??
        null,
);

const latestThreshold = computed(
    () =>
        props.fitnessMarkers.threshold[
            props.fitnessMarkers.threshold.length - 1
        ] ?? null,
);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Records', href: recordsIndex() }],
    },
});

const CHART_W = 720;
const CHART_H = 220;
const PAD = 16;

const hasCurve = computed(() => props.meanMax.length >= 2);

const yBounds = computed(() => {
    const speeds = props.meanMax.map((m) => m.speed_mps);
    const min = Math.min(...speeds) * 0.95;
    const max = Math.max(...speeds) * 1.02;

    return { min, max: max === min ? min + 1 : max };
});

function xAt(index: number): number {
    const n = props.meanMax.length;

    return n <= 1 ? 0 : (index / (n - 1)) * (CHART_W - PAD * 2) + PAD;
}

function yAt(speed: number): number {
    const { min, max } = yBounds.value;

    return PAD + ((max - speed) / (max - min)) * (CHART_H - PAD * 2);
}

const curvePath = computed(() =>
    props.meanMax
        .map(
            (m, i) =>
                `${i === 0 ? 'M' : 'L'} ${xAt(i).toFixed(1)} ${yAt(m.speed_mps).toFixed(1)}`,
        )
        .join(' '),
);

function sportHref(sport: string): string {
    return `${recordsIndex().url}?sport=${sport}`;
}

function sportLabel(sport: string): string {
    return sport.charAt(0).toUpperCase() + sport.slice(1);
}
</script>

<template>
    <Head title="Records" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <Heading
            title="Records"
            description="Personal bests and your pace curve"
        />

        <!-- Personal bests -->
        <section>
            <h2 class="mb-3 text-sm font-bold">Running personal bests</h2>
            <div
                v-if="records.length"
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
            >
                <Link
                    v-for="record in records"
                    :key="record.distance_m"
                    :href="show(record.activity_id)"
                    class="rounded-xl border bg-card p-4 transition-colors hover:border-primary/50"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-medium text-muted-foreground"
                            >{{ record.distance_label }}</span
                        >
                        <span
                            v-if="record.is_recent"
                            class="rounded bg-primary/15 px-1.5 py-0.5 text-[10px] font-semibold text-primary"
                            >New</span
                        >
                    </div>
                    <div
                        class="mt-1 text-2xl font-extrabold tracking-tight tabular-nums"
                    >
                        {{ record.time }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ record.pace }} · {{ record.achieved_on }}
                    </div>
                </Link>
            </div>
            <p
                v-else
                class="rounded-xl border bg-card px-4 py-8 text-center text-sm text-muted-foreground"
            >
                No personal bests yet. Sync a few runs with GPS to build them.
            </p>
        </section>

        <!-- Mean-max pace curve -->
        <section class="rounded-xl border bg-card p-5">
            <div
                class="mb-4 flex flex-wrap items-baseline justify-between gap-2"
            >
                <div>
                    <h2 class="text-sm font-bold">Pace curve</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Best sustained pace by duration
                    </p>
                </div>
                <div
                    v-if="availableSports.length > 1"
                    class="flex rounded-lg border p-0.5 text-xs"
                >
                    <Link
                        v-for="s in availableSports"
                        :key="s"
                        :href="sportHref(s)"
                        preserve-scroll
                        class="rounded-md px-2.5 py-1 font-medium"
                        :class="
                            sport === s
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        {{ sportLabel(s) }}
                    </Link>
                </div>
            </div>

            <div v-if="hasCurve">
                <svg
                    :viewBox="`0 0 ${CHART_W} ${CHART_H}`"
                    preserveAspectRatio="none"
                    class="h-52 w-full overflow-visible"
                >
                    <path
                        :d="curvePath"
                        fill="none"
                        class="stroke-primary"
                        stroke-width="2.5"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                    <g v-for="(point, i) in meanMax" :key="point.duration_s">
                        <circle
                            :cx="xAt(i)"
                            :cy="yAt(point.speed_mps)"
                            r="3.5"
                            class="fill-primary"
                        />
                    </g>
                </svg>
                <div
                    class="mt-2 flex justify-between text-[11px] text-muted-foreground"
                >
                    <span v-for="point in meanMax" :key="point.duration_s">
                        {{ point.duration_label }}
                    </span>
                </div>
                <div
                    class="mt-1 flex justify-between text-[11px] font-medium tabular-nums"
                >
                    <span v-for="point in meanMax" :key="point.duration_s">
                        {{ point.pace }}
                    </span>
                </div>
            </div>
            <p v-else class="py-10 text-center text-sm text-muted-foreground">
                Not enough data for a pace curve yet.
            </p>
        </section>

        <section class="rounded-xl border bg-card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold">Public profile</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Share a read-only snapshot: current form, PRs and a
                        fitness sparkline. No wellness or activity detail.
                    </p>
                </div>
                <button
                    v-if="!shareToken"
                    class="rounded-md bg-primary px-3 py-1.5 text-sm font-semibold text-primary-foreground"
                    @click="enableShare"
                >
                    Enable link
                </button>
                <button
                    v-else
                    class="rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-muted"
                    @click="disableShare"
                >
                    Disable
                </button>
            </div>
            <div
                v-if="shareUrl"
                class="mt-3 rounded-md border bg-background px-3 py-2 text-xs break-all text-muted-foreground"
            >
                <a :href="shareUrl" target="_blank" class="hover:underline">{{
                    shareUrl
                }}</a>
            </div>
            <div class="mt-3 border-t pt-3">
                <a
                    :href="exportAll().url"
                    class="text-xs font-medium text-primary hover:underline"
                    >Export all activities (CSV)</a
                >
            </div>
        </section>

        <section v-if="hasMarkers" class="rounded-xl border bg-card p-5">
            <div
                class="mb-4 flex flex-wrap items-baseline justify-between gap-2"
            >
                <div>
                    <h2 class="text-sm font-bold">Fitness markers</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        VO2max and derived threshold pace, weekly
                    </p>
                </div>
                <div class="flex gap-4 text-xs">
                    <span class="flex items-center gap-1.5">
                        <span class="h-2 w-3 rounded-sm bg-primary" />
                        VO2max
                        <span v-if="latestVo2" class="font-semibold">{{
                            latestVo2.value
                        }}</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-2 w-3 rounded-sm bg-sky-500" />
                        Threshold
                        <span v-if="latestThreshold" class="font-semibold">{{
                            thresholdPace(latestThreshold.pace_s_per_km)
                        }}</span>
                    </span>
                </div>
            </div>
            <svg
                viewBox="0 0 100 40"
                preserveAspectRatio="none"
                class="h-32 w-full"
            >
                <polyline
                    v-if="vo2Spark"
                    :points="vo2Spark"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    class="text-primary"
                    vector-effect="non-scaling-stroke"
                />
                <polyline
                    v-if="thresholdSpark"
                    :points="thresholdSpark"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    class="text-sky-500"
                    vector-effect="non-scaling-stroke"
                />
            </svg>
            <p class="mt-2 text-xs text-muted-foreground">
                Lines are scaled to their own range so both trends read on one
                chart; higher is fitter for both.
            </p>
        </section>
    </div>
</template>
