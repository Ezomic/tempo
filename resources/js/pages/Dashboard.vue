<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { edit as garminSettings } from '@/routes/garmin';

interface Readiness {
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

const props = defineProps<{
    hasData: boolean;
    garminConnected: boolean;
    readiness: Readiness | null;
    load: Load;
    weekly: Week[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const maxWeekly = computed(() =>
    Math.max(1, ...props.weekly.map((w) => w.total)),
);

const verdictClasses: Record<string, string> = {
    ready: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    caution: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    rest: 'bg-red-500/15 text-red-600 dark:text-red-400',
};

const verdictLabel: Record<string, string> = {
    ready: 'Ready to train',
    caution: 'Train with caution',
    rest: 'Prioritise rest',
};

const ratioClasses: Record<string, string> = {
    optimal: 'text-emerald-600 dark:text-emerald-400',
    high: 'text-red-600 dark:text-red-400',
    low: 'text-amber-600 dark:text-amber-400',
    unknown: 'text-muted-foreground',
};

const ratioLabel: Record<string, string> = {
    optimal: 'In range',
    high: 'Load spiking',
    low: 'Building',
    unknown: 'Not enough data',
};

function segmentHeight(value: number): string {
    return `${(value / maxWeekly.value) * 100}%`;
}

function weekLabel(iso: string): string {
    const d = new Date(iso);

    return `${d.getDate()}/${d.getMonth() + 1}`;
}

function titleCase(value: string | null): string {
    if (!value) {
        return '—';
    }

    return value.charAt(0).toUpperCase() + value.slice(1);
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <Card v-if="!hasData">
            <CardHeader>
                <CardTitle>No training data yet</CardTitle>
                <CardDescription>
                    Connect your Garmin account and run a sync to see your load
                    and readiness here.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Link
                    :href="garminSettings()"
                    class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground"
                >
                    {{ garminConnected ? 'Sync settings' : 'Connect Garmin' }}
                </Link>
            </CardContent>
        </Card>

        <template v-else>
            <div class="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Readiness</CardTitle>
                        <CardDescription v-if="readiness">
                            From your wellness on {{ readiness.date }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="readiness" class="space-y-4">
                            <span
                                class="inline-flex rounded-md px-3 py-1 text-sm font-semibold"
                                :class="verdictClasses[readiness.verdict]"
                            >
                                {{ verdictLabel[readiness.verdict] }}
                            </span>
                            <div class="grid grid-cols-3 gap-4 text-sm">
                                <div>
                                    <div class="text-muted-foreground">HRV</div>
                                    <div class="font-medium">
                                        {{ titleCase(readiness.hrv_status) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-muted-foreground">
                                        Body battery
                                    </div>
                                    <div class="font-medium">
                                        {{ readiness.body_battery ?? '—' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-muted-foreground">
                                        Resting HR
                                    </div>
                                    <div class="font-medium">
                                        {{ readiness.resting_hr ?? '—' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            No wellness data synced yet.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Training load</CardTitle>
                        <CardDescription>
                            Acute vs chronic, run and bike combined (TRIMP)
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-3 gap-4 text-sm">
                            <div>
                                <div class="text-muted-foreground">
                                    7-day load
                                </div>
                                <div class="text-2xl font-semibold">
                                    {{ load.acute }}
                                </div>
                            </div>
                            <div>
                                <div class="text-muted-foreground">
                                    Weekly avg
                                </div>
                                <div class="text-2xl font-semibold">
                                    {{ load.chronic_weekly }}
                                </div>
                            </div>
                            <div>
                                <div class="text-muted-foreground">
                                    Acute:chronic
                                </div>
                                <div
                                    class="text-2xl font-semibold"
                                    :class="ratioClasses[load.status]"
                                >
                                    {{ load.ratio ?? '—' }}
                                </div>
                                <div
                                    class="text-xs"
                                    :class="ratioClasses[load.status]"
                                >
                                    {{ ratioLabel[load.status] }}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Weekly load</CardTitle>
                    <CardDescription>Last 8 weeks by sport</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex items-end gap-2">
                        <div
                            v-for="week in weekly"
                            :key="week.week_start"
                            class="flex flex-1 flex-col items-center gap-2"
                        >
                            <div
                                class="flex h-40 w-full flex-col-reverse overflow-hidden rounded"
                                :title="`${week.total} TRIMP`"
                            >
                                <div
                                    class="w-full bg-sky-500"
                                    :style="{ height: segmentHeight(week.run) }"
                                />
                                <div
                                    class="w-full bg-emerald-500"
                                    :style="{
                                        height: segmentHeight(week.bike),
                                    }"
                                />
                                <div
                                    class="w-full bg-zinc-400"
                                    :style="{
                                        height: segmentHeight(week.other),
                                    }"
                                />
                            </div>
                            <span class="text-xs text-muted-foreground">
                                {{ weekLabel(week.week_start) }}
                            </span>
                        </div>
                    </div>
                    <div
                        class="flex gap-4 text-xs text-muted-foreground"
                        aria-hidden="true"
                    >
                        <span class="flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-sky-500" />
                            Run
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-emerald-500" />
                            Bike
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-zinc-400" />
                            Other
                        </span>
                    </div>
                </CardContent>
            </Card>
        </template>
    </div>
</template>
