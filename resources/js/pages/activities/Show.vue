<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as activitiesIndex } from '@/routes/activities';

interface Activity {
    id: number;
    sport: string;
    sub_sport: string | null;
    started_at: string;
    duration_s: number | null;
    moving_time_s: number | null;
    distance_m: number | null;
    avg_hr: number | null;
    max_hr: number | null;
    elevation_gain_m: number | null;
    avg_speed_mps: number | null;
    calories: number | null;
    trimp: number | null;
    hr_zone_seconds: Record<string, number> | null;
}

const props = defineProps<{ activity: Activity }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Activities', href: activitiesIndex() }],
    },
});

const zoneColors: Record<number, string> = {
    1: 'bg-sky-500',
    2: 'bg-emerald-500',
    3: 'bg-amber-500',
    4: 'bg-orange-500',
    5: 'bg-red-500',
};

const zones = computed(() =>
    [1, 2, 3, 4, 5].map((zone) => ({
        zone,
        seconds: props.activity.hr_zone_seconds?.[zone] ?? 0,
    })),
);

const maxZone = computed(() =>
    Math.max(1, ...zones.value.map((z) => z.seconds)),
);

const hasZones = computed(() => zones.value.some((z) => z.seconds > 0));

function duration(seconds: number | null): string {
    if (seconds === null) {
        return '—';
    }

    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    const mm = String(m).padStart(2, '0');
    const ss = String(s).padStart(2, '0');

    return h > 0 ? `${h}:${mm}:${ss}` : `${m}:${ss}`;
}

function km(m: number | null): string {
    return m === null ? '—' : `${(m / 1000).toFixed(2)} km`;
}

function speed(mps: number | null): string {
    return mps === null ? '—' : `${(mps * 3.6).toFixed(1)} km/h`;
}

function sportLabel(sport: string): string {
    return sport.charAt(0).toUpperCase() + sport.slice(1);
}

const stats = computed(() => [
    { label: 'Distance', value: km(props.activity.distance_m) },
    { label: 'Duration', value: duration(props.activity.duration_s) },
    { label: 'Moving time', value: duration(props.activity.moving_time_s) },
    { label: 'Avg HR', value: props.activity.avg_hr ?? '—' },
    { label: 'Max HR', value: props.activity.max_hr ?? '—' },
    { label: 'Avg speed', value: speed(props.activity.avg_speed_mps) },
    {
        label: 'Elevation',
        value:
            props.activity.elevation_gain_m === null
                ? '—'
                : `${Math.round(props.activity.elevation_gain_m)} m`,
    },
    { label: 'Calories', value: props.activity.calories ?? '—' },
    { label: 'Load (TRIMP)', value: props.activity.trimp ?? '—' },
]);
</script>

<template>
    <Head title="Activity" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                :title="sportLabel(activity.sport)"
                :description="new Date(activity.started_at).toLocaleString()"
            />
            <Link
                :href="activitiesIndex()"
                class="text-sm text-muted-foreground hover:underline"
            >
                Back to activities
            </Link>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Summary</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                    <div v-for="stat in stats" :key="stat.label">
                        <div class="text-muted-foreground">
                            {{ stat.label }}
                        </div>
                        <div class="text-lg font-semibold">
                            {{ stat.value }}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card v-if="hasZones">
            <CardHeader>
                <CardTitle>Heart-rate zones</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2">
                <div
                    v-for="z in zones"
                    :key="z.zone"
                    class="flex items-center gap-3 text-sm"
                >
                    <span class="w-6 text-muted-foreground">Z{{ z.zone }}</span>
                    <div class="h-4 flex-1 overflow-hidden rounded bg-muted">
                        <div
                            class="h-full rounded"
                            :class="zoneColors[z.zone]"
                            :style="{
                                width: `${(z.seconds / maxZone) * 100}%`,
                            }"
                        />
                    </div>
                    <span class="w-16 text-right tabular-nums">
                        {{ duration(z.seconds) }}
                    </span>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
