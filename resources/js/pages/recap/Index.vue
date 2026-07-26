<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { index as recapIndex } from '@/routes/recap';

interface SportTotals {
    distance_m: number;
    hours: number;
    activities: number;
}

interface Recap {
    totals: {
        distance_m: number;
        elevation_m: number;
        hours: number;
        activities: number;
    };
    by_sport: Record<string, SportTotals>;
    prs: number;
    ctl_delta: number;
    ctl_start: number;
    ctl_end: number;
}

interface Consistency {
    days: { date: string; load: number; level: number }[];
    current_streak: number;
    longest_streak: number;
    active_days: number;
}

const props = defineProps<{
    recap: Recap;
    period: string;
    range: { from: string; to: string };
    consistency: Consistency;
}>();

const levelClass: Record<number, string> = {
    0: 'bg-muted',
    1: 'bg-primary/25',
    2: 'bg-primary/50',
    3: 'bg-primary/75',
    4: 'bg-primary',
};

const heatmapWeeks = computed(() => {
    const weeks: { date: string; load: number; level: number }[][] = [];

    for (let i = 0; i < props.consistency.days.length; i += 7) {
        weeks.push(props.consistency.days.slice(i, i + 7));
    }

    return weeks;
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Recap', href: recapIndex() }],
    },
});

const headline = computed(() => [
    {
        label: 'Distance',
        value: `${(props.recap.totals.distance_m / 1000).toFixed(1)} km`,
    },
    { label: 'Time', value: `${props.recap.totals.hours} h` },
    {
        label: 'Elevation',
        value: `${Math.round(props.recap.totals.elevation_m)} m`,
    },
    { label: 'Sessions', value: `${props.recap.totals.activities}` },
    { label: 'PRs', value: `${props.recap.prs}` },
    {
        label: 'Fitness',
        value: `${props.recap.ctl_delta >= 0 ? '+' : ''}${props.recap.ctl_delta} CTL`,
    },
]);

const sports = computed(() => Object.entries(props.recap.by_sport));
</script>

<template>
    <Head title="Recap" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading
                title="Training recap"
                :description="`${range.from} to ${range.to}`"
            />
            <div class="flex rounded-lg border p-0.5 text-xs">
                <Link
                    v-for="p in ['month', 'year']"
                    :key="p"
                    :href="`${recapIndex().url}?period=${p}`"
                    preserve-scroll
                    class="rounded-md px-2.5 py-1 font-medium capitalize"
                    :class="
                        period === p
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                >
                    {{ p }}
                </Link>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div
                v-for="item in headline"
                :key="item.label"
                class="rounded-xl border bg-card p-4"
            >
                <div class="text-xs text-muted-foreground">
                    {{ item.label }}
                </div>
                <div class="mt-1 text-xl font-bold tabular-nums">
                    {{ item.value }}
                </div>
            </div>
        </div>

        <!-- Consistency heatmap -->
        <section class="rounded-xl border bg-card p-5">
            <div
                class="mb-4 flex flex-wrap items-baseline justify-between gap-2"
            >
                <div>
                    <h2 class="text-sm font-bold">Consistency</h2>
                    <p
                        class="mt-0.5 font-mono text-[11px] text-muted-foreground"
                    >
                        Training days over the last year
                    </p>
                </div>
                <div class="flex gap-4 text-xs">
                    <span
                        ><span class="font-semibold text-foreground">{{
                            consistency.current_streak
                        }}</span>
                        day streak</span
                    >
                    <span
                        >longest
                        <span class="font-semibold text-foreground">{{
                            consistency.longest_streak
                        }}</span></span
                    >
                    <span
                        ><span class="font-semibold text-foreground">{{
                            consistency.active_days
                        }}</span>
                        active days</span
                    >
                </div>
            </div>

            <div class="overflow-x-auto">
                <div class="flex gap-1">
                    <div
                        v-for="(week, wi) in heatmapWeeks"
                        :key="wi"
                        class="flex flex-col gap-1"
                    >
                        <span
                            v-for="day in week"
                            :key="day.date"
                            class="h-2.5 w-2.5 rounded-[2px]"
                            :class="levelClass[day.level]"
                            :title="`${day.date}: ${day.load}`"
                        />
                    </div>
                </div>
            </div>
        </section>

        <section v-if="sports.length" class="rounded-xl border bg-card p-5">
            <h2 class="mb-3 text-sm font-bold">By sport</h2>
            <div class="space-y-2">
                <div
                    v-for="[sport, totals] in sports"
                    :key="sport"
                    class="flex items-center justify-between rounded-lg border bg-background px-3 py-2 text-sm"
                >
                    <span class="font-medium capitalize">{{ sport }}</span>
                    <span class="text-muted-foreground">
                        {{ (totals.distance_m / 1000).toFixed(1) }} km ·
                        {{ totals.hours }} h · {{ totals.activities }} sessions
                    </span>
                </div>
            </div>
        </section>

        <p
            v-else
            class="rounded-xl border bg-card p-8 text-center text-sm text-muted-foreground"
        >
            No activities in this period yet.
        </p>
    </div>
</template>
