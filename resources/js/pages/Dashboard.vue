<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { dashboard } from '@/routes';
import { index as activitiesIndex, show } from '@/routes/activities';
import { edit as garminSettings } from '@/routes/garmin';
import { index as planIndex } from '@/routes/plan';

interface Readiness {
    score: number;
    verdict: string;
    hrv_status: string | null;
    body_battery: number | null;
    resting_hr: number | null;
    date: string;
}

interface Load {
    acute: number;
    chronic_weekly: number;
    ratio: number | null;
    status: string;
}

interface Week {
    week_start: string;
    run: number;
    bike: number;
    other: number;
    total: number;
}

interface Guardrails {
    acwr: number | null;
    acwr_band: string;
    ramp_pct: number | null;
    ramp_band: string;
    status: string;
    message: string;
}

interface CurvePoint {
    date: string;
    ctl: number;
    atl: number;
    tsb: number;
}

interface FitnessCurve {
    current: CurvePoint | null;
    history: CurvePoint[];
    projection: CurvePoint[];
}

interface Activity {
    id: number;
    sport: string;
    name: string;
    distance_m: number | null;
    duration_s: number | null;
    trimp: number | null;
}

interface TodayPlan {
    sport: string;
    title: string;
    duration_min: number | null;
    notes: string | null;
    pushed: boolean;
}

const props = defineProps<{
    hasData: boolean;
    garminConnected: boolean;
    readiness: Readiness | null;
    load: Load;
    guardrails: Guardrails;
    fitnessCurve: FitnessCurve;
    weekly: Week[];
    recentActivities: Activity[];
    todayPlan: TodayPlan | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const RING_CIRCUMFERENCE = 2 * Math.PI * 52;

const ringOffset = computed(() => {
    const score = props.readiness?.score ?? 0;

    return RING_CIRCUMFERENCE * (1 - score / 100);
});

const verdictStroke = computed<string>(
    () =>
        ({
            ready: 'stroke-primary',
            caution: 'stroke-amber-500',
            rest: 'stroke-red-500',
        })[props.readiness?.verdict ?? 'ready'] ?? 'stroke-primary',
);

const verdictText = computed<string>(
    () =>
        ({
            ready: 'text-primary',
            caution: 'text-amber-500',
            rest: 'text-red-500',
        })[props.readiness?.verdict ?? 'ready'] ?? 'text-primary',
);

const verdictLabel: Record<string, string> = {
    ready: 'Ready to train',
    caution: 'Train with caution',
    rest: 'Prioritise rest',
};

const ratioClasses: Record<string, string> = {
    optimal: 'text-primary',
    high: 'text-red-500',
    low: 'text-amber-500',
    unknown: 'text-muted-foreground',
};

const ratioLabel: Record<string, string> = {
    optimal: 'In range',
    high: 'Load spiking',
    low: 'Building',
    unknown: 'Not enough data',
};

const guardrailBanner: Record<string, string> = {
    danger: 'border-red-500/40 bg-red-500/10 text-red-600 dark:text-red-400',
    caution:
        'border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-amber-400',
};

const showGuardrail = computed(
    () =>
        props.guardrails.status === 'caution' ||
        props.guardrails.status === 'danger',
);

const rampLabel = computed<string>(() => {
    const ramp = props.guardrails.ramp_pct;

    if (ramp === null) {
        return '—';
    }

    return `${ramp > 0 ? '+' : ''}${ramp}%`;
});

const maxWeekly = computed(() =>
    Math.max(1, ...props.weekly.map((w) => w.total)),
);

const acwrMark = computed(() => {
    const r = props.load.ratio ?? 0;

    return `${Math.min(100, Math.max(0, (r / 2) * 100))}%`;
});

function pct(value: number): string {
    return `${(value / maxWeekly.value) * 100}%`;
}

function titleCase(value: string | null): string {
    return value ? value.charAt(0).toUpperCase() + value.slice(1) : '—';
}

function weekLabel(iso: string): string {
    const d = new Date(iso);

    return `${d.getDate()}/${d.getMonth() + 1}`;
}

function km(m: number | null): string {
    return m === null ? '—' : `${(m / 1000).toFixed(1)} km`;
}

type CurveWindow = '6w' | '3m' | '1y';

const CHART_W = 720;
const CHART_H = 200;
const PAD_Y = 14;

const curveWindow = ref<CurveWindow>('6w');
const curveDays: Record<CurveWindow, number> = {
    '6w': 42,
    '3m': 90,
    '1y': 365,
};
const curveWindows: CurveWindow[] = ['6w', '3m', '1y'];

const historyShown = computed(() =>
    props.fitnessCurve.history.slice(-curveDays[curveWindow.value]),
);

const curvePoints = computed(() => [
    ...historyShown.value,
    ...props.fitnessCurve.projection,
]);

const hasCurve = computed(() => curvePoints.value.length >= 2);

const yBounds = computed(() => {
    const values = curvePoints.value.flatMap((p) => [p.ctl, p.atl, p.tsb, 0]);
    const min = Math.min(...values);
    let max = Math.max(...values);

    if (min === max) {
        max = min + 1;
    }

    return { min, max };
});

function xAt(index: number): number {
    const n = curvePoints.value.length;

    return n <= 1 ? 0 : (index / (n - 1)) * CHART_W;
}

function yAt(value: number): number {
    const { min, max } = yBounds.value;
    const plotH = CHART_H - PAD_Y * 2;

    return PAD_Y + ((max - value) / (max - min)) * plotH;
}

function linePath(
    getter: (p: CurvePoint) => number,
    from: number,
    to: number,
): string {
    let d = '';

    for (let i = from; i < to; i++) {
        const point = curvePoints.value[i];
        d += `${i === from ? 'M' : 'L'} ${xAt(i).toFixed(1)} ${yAt(getter(point)).toFixed(1)} `;
    }

    return d.trim();
}

const historyEnd = computed(() => historyShown.value.length);
const projectionStart = computed(() => Math.max(0, historyEnd.value - 1));
const hasProjection = computed(
    () => props.fitnessCurve.projection.length > 0 && historyEnd.value > 0,
);

const ctlHistoryPath = computed(() =>
    linePath((p) => p.ctl, 0, historyEnd.value),
);
const atlHistoryPath = computed(() =>
    linePath((p) => p.atl, 0, historyEnd.value),
);
const ctlProjectionPath = computed(() =>
    hasProjection.value
        ? linePath(
              (p) => p.ctl,
              projectionStart.value,
              curvePoints.value.length,
          )
        : '',
);
const atlProjectionPath = computed(() =>
    hasProjection.value
        ? linePath(
              (p) => p.atl,
              projectionStart.value,
              curvePoints.value.length,
          )
        : '',
);

const zeroLineY = computed(() => yAt(0));

const tsbAreaPath = computed(() => {
    const end = historyEnd.value;

    if (end === 0) {
        return '';
    }

    const zero = zeroLineY.value.toFixed(1);
    let d = `M ${xAt(0).toFixed(1)} ${zero} `;

    for (let i = 0; i < end; i++) {
        d += `L ${xAt(i).toFixed(1)} ${yAt(curvePoints.value[i].tsb).toFixed(1)} `;
    }

    d += `L ${xAt(end - 1).toFixed(1)} ${zero} Z`;

    return d;
});

const todayX = computed(() => xAt(Math.max(0, historyEnd.value - 1)));

const formClass = computed<string>(() => {
    const tsb = props.fitnessCurve.current?.tsb ?? 0;

    return tsb >= 5
        ? 'text-primary'
        : tsb >= -10
          ? 'text-foreground'
          : tsb >= -30
            ? 'text-amber-500'
            : 'text-red-500';
});

const formLabel = computed<string>(() => {
    const tsb = props.fitnessCurve.current?.tsb ?? 0;

    return tsb >= 15
        ? 'Fresh'
        : tsb >= 5
          ? 'Rested'
          : tsb >= -10
            ? 'Neutral'
            : tsb >= -30
              ? 'Fatigued'
              : 'Very fatigued';
});

function duration(seconds: number | null): string {
    if (seconds === null) {
        return '—';
    }

    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;

    return h > 0
        ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
        : `${m}:${String(s).padStart(2, '0')}`;
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <!-- Empty state -->
        <div
            v-if="!hasData"
            class="flex flex-col items-center justify-center gap-3 rounded-xl border bg-card px-6 py-16 text-center"
        >
            <div class="text-lg font-semibold">No training data yet</div>
            <p class="max-w-sm text-sm text-muted-foreground">
                Connect your Garmin account and run a sync to see your readiness
                and load here.
            </p>
            <Link
                :href="garminSettings()"
                class="mt-2 inline-flex h-9 items-center rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground"
            >
                {{ garminConnected ? 'Sync settings' : 'Connect Garmin' }}
            </Link>
        </div>

        <template v-else>
            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Readiness -->
                <section class="rounded-xl border bg-card p-5">
                    <div class="mb-1 flex items-baseline justify-between">
                        <h2 class="text-sm font-bold">Readiness</h2>
                        <span class="text-xs text-muted-foreground">
                            {{ readiness ? readiness.date : 'No wellness yet' }}
                        </span>
                    </div>

                    <div v-if="readiness" class="space-y-5">
                        <div class="relative mx-auto mt-2 size-44">
                            <svg
                                viewBox="0 0 120 120"
                                class="size-44 -rotate-90"
                            >
                                <circle
                                    cx="60"
                                    cy="60"
                                    r="52"
                                    fill="none"
                                    class="stroke-muted"
                                    stroke-width="10"
                                />
                                <circle
                                    cx="60"
                                    cy="60"
                                    r="52"
                                    fill="none"
                                    stroke-linecap="round"
                                    stroke-width="10"
                                    :class="verdictStroke"
                                    :stroke-dasharray="RING_CIRCUMFERENCE"
                                    :stroke-dashoffset="ringOffset"
                                />
                            </svg>
                            <div
                                class="absolute inset-0 flex flex-col items-center justify-center"
                            >
                                <div
                                    class="text-4xl font-extrabold tracking-tight"
                                >
                                    {{ readiness.score }}
                                </div>
                                <div
                                    class="mt-1 text-sm font-semibold"
                                    :class="verdictText"
                                >
                                    {{ verdictLabel[readiness.verdict] }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div
                                class="flex items-center justify-between rounded-lg border bg-background px-3 py-2.5 text-sm"
                            >
                                <span class="text-muted-foreground">HRV</span>
                                <span class="font-semibold">{{
                                    titleCase(readiness.hrv_status)
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between rounded-lg border bg-background px-3 py-2.5 text-sm"
                            >
                                <span class="text-muted-foreground"
                                    >Body battery</span
                                >
                                <span class="font-semibold">{{
                                    readiness.body_battery ?? '—'
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between rounded-lg border bg-background px-3 py-2.5 text-sm"
                            >
                                <span class="text-muted-foreground"
                                    >Resting HR</span
                                >
                                <span class="font-semibold">{{
                                    readiness.resting_hr ?? '—'
                                }}</span>
                            </div>
                        </div>
                    </div>
                    <p
                        v-else
                        class="py-10 text-center text-sm text-muted-foreground"
                    >
                        No wellness data synced yet.
                    </p>
                </section>

                <!-- Load + ACWR -->
                <section class="rounded-xl border bg-card p-5 lg:col-span-2">
                    <div class="mb-4 flex items-baseline justify-between">
                        <h2 class="text-sm font-bold">Training load</h2>
                        <span class="text-xs text-muted-foreground">
                            Run + bike, one scale (TRIMP)
                        </span>
                    </div>

                    <div
                        v-if="showGuardrail"
                        class="mb-4 flex items-start gap-2 rounded-lg border px-3 py-2.5 text-xs"
                        :class="guardrailBanner[guardrails.status]"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="mt-px size-4 shrink-0"
                        >
                            <path
                                d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"
                            />
                            <line x1="12" y1="9" x2="12" y2="13" />
                            <line x1="12" y1="17" x2="12.01" y2="17" />
                        </svg>
                        <span class="font-medium">{{
                            guardrails.message
                        }}</span>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <div class="text-3xl font-extrabold tracking-tight">
                                {{ load.acute }}
                            </div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                7-day acute
                            </div>
                        </div>
                        <div>
                            <div class="text-3xl font-extrabold tracking-tight">
                                {{ load.chronic_weekly }}
                            </div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                Weekly average
                            </div>
                        </div>
                        <div>
                            <div
                                class="text-3xl font-extrabold tracking-tight"
                                :class="ratioClasses[load.status]"
                            >
                                {{ load.ratio ?? '—' }}
                            </div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                Acute : chronic
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <div
                            class="relative h-2.5 rounded-full border bg-background"
                        >
                            <div
                                class="absolute inset-y-0 rounded-full bg-primary/25"
                                style="left: 40%; width: 25%"
                            />
                            <div
                                v-if="load.ratio !== null"
                                class="absolute top-1/2 size-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-card bg-primary"
                                :style="{ left: acwrMark }"
                            />
                        </div>
                        <div
                            class="mt-2 flex justify-between text-[11px] text-muted-foreground"
                        >
                            <span>0.8</span>
                            <span
                                class="font-medium"
                                :class="ratioClasses[load.status]"
                                >{{ ratioLabel[load.status] }}</span
                            >
                            <span>1.3</span>
                        </div>
                        <div
                            class="mt-3 flex items-center justify-between text-xs"
                        >
                            <span class="text-muted-foreground"
                                >Weekly ramp</span
                            >
                            <span class="font-semibold tabular-nums">{{
                                rampLabel
                            }}</span>
                        </div>
                    </div>

                    <!-- Weekly bars -->
                    <div class="mt-6 border-t pt-5">
                        <div class="mb-3 flex items-center justify-between">
                            <h3
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                Weekly load · last 8 weeks
                            </h3>
                            <div
                                class="flex items-center gap-3 text-[11px] text-muted-foreground"
                            >
                                <span class="flex items-center gap-1.5"
                                    ><i
                                        class="size-2 rounded-full bg-sky-500"
                                    ></i
                                    >Run</span
                                >
                                <span class="flex items-center gap-1.5"
                                    ><i
                                        class="size-2 rounded-full bg-emerald-500"
                                    ></i
                                    >Bike</span
                                >
                            </div>
                        </div>
                        <div class="flex h-32 items-end gap-2">
                            <div
                                v-for="(week, i) in weekly"
                                :key="week.week_start"
                                class="flex h-full flex-1 flex-col items-center justify-end gap-2"
                            >
                                <div
                                    class="flex h-full w-full flex-col-reverse overflow-hidden rounded-md"
                                    :class="
                                        i === weekly.length - 1
                                            ? 'outline outline-2 outline-offset-2 outline-primary'
                                            : ''
                                    "
                                    :title="`${week.total} TRIMP`"
                                >
                                    <span
                                        class="w-full bg-sky-500"
                                        :style="{ height: pct(week.run) }"
                                    />
                                    <span
                                        class="w-full bg-emerald-500"
                                        :style="{ height: pct(week.bike) }"
                                    />
                                    <span
                                        class="w-full bg-muted-foreground/40"
                                        :style="{ height: pct(week.other) }"
                                    />
                                </div>
                                <span
                                    class="text-[11px] text-muted-foreground"
                                    >{{ weekLabel(week.week_start) }}</span
                                >
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Fitness / fatigue / form -->
            <section class="rounded-xl border bg-card p-5">
                <div
                    class="mb-4 flex flex-wrap items-baseline justify-between gap-2"
                >
                    <div>
                        <h2 class="text-sm font-bold">Fitness &amp; form</h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            CTL / ATL / TSB · dashed is projected from your plan
                        </p>
                    </div>
                    <div class="flex rounded-lg border p-0.5 text-xs">
                        <button
                            v-for="w in curveWindows"
                            :key="w"
                            type="button"
                            class="rounded-md px-2.5 py-1 font-medium"
                            :class="
                                curveWindow === w
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="curveWindow = w"
                        >
                            {{ w }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <div
                            class="text-3xl font-extrabold tracking-tight text-primary"
                        >
                            {{ fitnessCurve.current?.ctl ?? '—' }}
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            Fitness (CTL)
                        </div>
                    </div>
                    <div>
                        <div
                            class="text-3xl font-extrabold tracking-tight text-amber-500"
                        >
                            {{ fitnessCurve.current?.atl ?? '—' }}
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            Fatigue (ATL)
                        </div>
                    </div>
                    <div>
                        <div
                            class="text-3xl font-extrabold tracking-tight"
                            :class="formClass"
                        >
                            {{ fitnessCurve.current?.tsb ?? '—' }}
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            Form (TSB) · {{ formLabel }}
                        </div>
                    </div>
                </div>

                <div v-if="hasCurve" class="mt-5">
                    <svg
                        :viewBox="`0 0 ${CHART_W} ${CHART_H}`"
                        preserveAspectRatio="none"
                        class="h-44 w-full overflow-visible"
                    >
                        <!-- zero line for form -->
                        <line
                            x1="0"
                            :x2="CHART_W"
                            :y1="zeroLineY"
                            :y2="zeroLineY"
                            class="stroke-border"
                            stroke-width="1"
                            stroke-dasharray="4 4"
                        />
                        <!-- form band -->
                        <path :d="tsbAreaPath" class="fill-sky-500/15" />
                        <!-- today divider -->
                        <line
                            v-if="hasProjection"
                            :x1="todayX"
                            :x2="todayX"
                            y1="0"
                            :y2="CHART_H"
                            class="stroke-muted-foreground/40"
                            stroke-width="1"
                            stroke-dasharray="2 3"
                        />
                        <!-- fitness / fatigue history -->
                        <path
                            :d="atlHistoryPath"
                            fill="none"
                            class="stroke-amber-500"
                            stroke-width="2"
                            stroke-linejoin="round"
                        />
                        <path
                            :d="ctlHistoryPath"
                            fill="none"
                            class="stroke-primary"
                            stroke-width="2.5"
                            stroke-linejoin="round"
                        />
                        <!-- projection -->
                        <path
                            v-if="hasProjection"
                            :d="atlProjectionPath"
                            fill="none"
                            class="stroke-amber-500/60"
                            stroke-width="2"
                            stroke-dasharray="5 4"
                            stroke-linejoin="round"
                        />
                        <path
                            v-if="hasProjection"
                            :d="ctlProjectionPath"
                            fill="none"
                            class="stroke-primary/60"
                            stroke-width="2.5"
                            stroke-dasharray="5 4"
                            stroke-linejoin="round"
                        />
                    </svg>
                    <div
                        class="mt-3 flex items-center gap-4 text-[11px] text-muted-foreground"
                    >
                        <span class="flex items-center gap-1.5"
                            ><i class="h-0.5 w-4 rounded bg-primary"></i
                            >Fitness</span
                        >
                        <span class="flex items-center gap-1.5"
                            ><i class="h-0.5 w-4 rounded bg-amber-500"></i
                            >Fatigue</span
                        >
                        <span class="flex items-center gap-1.5"
                            ><i class="size-2 rounded-sm bg-sky-500/40"></i
                            >Form</span
                        >
                    </div>
                </div>
                <p
                    v-else
                    class="py-10 text-center text-sm text-muted-foreground"
                >
                    Not enough history yet. Sync a few more days of activity to
                    see your fitness curve.
                </p>
            </section>

            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Recent -->
                <section class="rounded-xl border bg-card p-5 lg:col-span-2">
                    <div class="mb-2 flex items-baseline justify-between">
                        <h2 class="text-sm font-bold">Recent activities</h2>
                        <Link
                            :href="activitiesIndex()"
                            class="text-xs text-muted-foreground hover:underline"
                            >View all</Link
                        >
                    </div>
                    <p
                        v-if="recentActivities.length === 0"
                        class="py-6 text-sm text-muted-foreground"
                    >
                        No activities yet.
                    </p>
                    <ul v-else class="divide-y">
                        <li
                            v-for="activity in recentActivities"
                            :key="activity.id"
                            class="grid grid-cols-[10px_1fr_auto_auto_44px] items-center gap-3 py-2.5 text-sm"
                        >
                            <span
                                class="size-2.5 rounded-full"
                                :class="
                                    activity.sport === 'bike'
                                        ? 'bg-emerald-500'
                                        : activity.sport === 'run'
                                          ? 'bg-sky-500'
                                          : 'bg-muted-foreground'
                                "
                            />
                            <Link
                                :href="show(activity.id)"
                                class="truncate font-medium hover:underline"
                                >{{ activity.name }}</Link
                            >
                            <span class="text-muted-foreground tabular-nums">{{
                                km(activity.distance_m)
                            }}</span>
                            <span class="text-muted-foreground tabular-nums">{{
                                duration(activity.duration_s)
                            }}</span>
                            <span
                                class="text-right font-semibold tabular-nums"
                                >{{ activity.trimp ?? '—' }}</span
                            >
                        </li>
                    </ul>
                </section>

                <!-- Today's plan -->
                <section class="flex flex-col rounded-xl border bg-card p-5">
                    <div class="mb-3 flex items-baseline justify-between">
                        <h2 class="text-sm font-bold">Today</h2>
                        <span class="text-xs text-muted-foreground"
                            >Planned</span
                        >
                    </div>
                    <template v-if="todayPlan">
                        <div
                            class="text-[11px] font-bold tracking-[0.12em] uppercase"
                            :class="
                                todayPlan.sport === 'bike'
                                    ? 'text-emerald-500'
                                    : 'text-sky-500'
                            "
                        >
                            {{ todayPlan.sport }}
                        </div>
                        <div class="mt-1 text-lg font-extrabold tracking-tight">
                            {{ todayPlan.title }}
                        </div>
                        <div
                            v-if="todayPlan.duration_min"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            {{ todayPlan.duration_min }} min
                        </div>
                        <div
                            v-if="todayPlan.notes"
                            class="mt-3 rounded-lg border bg-background px-3 py-2 text-xs text-muted-foreground"
                        >
                            {{ todayPlan.notes }}
                        </div>
                    </template>
                    <div
                        v-else
                        class="flex flex-1 flex-col items-start justify-center gap-3 py-4"
                    >
                        <p class="text-sm text-muted-foreground">
                            Nothing planned for today.
                        </p>
                        <Link
                            :href="planIndex()"
                            class="inline-flex h-9 items-center rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground"
                        >
                            Plan a workout
                        </Link>
                    </div>
                </section>
            </div>
        </template>
    </div>
</template>
