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

const props = defineProps<{
    recap: Recap;
    period: string;
    range: { from: string; to: string };
}>();

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
