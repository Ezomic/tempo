<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Record {
    distance_m: number;
    time: string;
    achieved_on: string;
}

interface Profile {
    name: string;
    form: number | null;
    ctl: number | null;
    records: Record[];
    sparkline: number[];
}

const props = defineProps<{
    profile: Profile;
}>();

const spark = computed(() => {
    const v = props.profile.sparkline;

    if (v.length < 2) {
        return '';
    }

    const min = Math.min(...v);
    const max = Math.max(...v);
    const span = max - min || 1;

    return v
        .map(
            (n, i) =>
                `${((i / (v.length - 1)) * 100).toFixed(1)},${(100 - ((n - min) / span) * 100).toFixed(1)}`,
        )
        .join(' ');
});

function distanceLabel(m: number): string {
    return m >= 1000
        ? `${(m / 1000).toFixed(m % 1000 === 0 ? 0 : 1)}K`
        : `${m}m`;
}
</script>

<template>
    <Head :title="`${profile.name} · Tempo`" />

    <div
        class="flex min-h-screen items-center justify-center bg-background p-6 text-foreground"
    >
        <div class="w-full max-w-lg space-y-6">
            <div class="text-center">
                <h1 class="text-2xl font-bold">{{ profile.name }}</h1>
                <p
                    class="font-mono text-xs tracking-wide text-muted-foreground"
                >
                    Training snapshot
                </p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl border bg-card p-4 text-center">
                    <div class="data-label">Fitness</div>
                    <div class="mt-1 text-2xl font-bold tabular-nums">
                        {{ profile.ctl ?? '-' }}
                    </div>
                </div>
                <div class="rounded-xl border bg-card p-4 text-center">
                    <div class="data-label">Form</div>
                    <div class="mt-1 text-2xl font-bold tabular-nums">
                        {{
                            profile.form === null
                                ? '-'
                                : `${profile.form > 0 ? '+' : ''}${profile.form}`
                        }}
                    </div>
                </div>
            </div>

            <div v-if="spark" class="rounded-xl border bg-card p-4">
                <div class="mb-2 data-label">Fitness, last 90 days</div>
                <svg
                    viewBox="0 0 100 40"
                    preserveAspectRatio="none"
                    class="h-16 w-full"
                >
                    <polyline
                        :points="spark"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        class="text-primary"
                        vector-effect="non-scaling-stroke"
                        transform="scale(1, 0.4)"
                    />
                </svg>
            </div>

            <div
                v-if="profile.records.length"
                class="rounded-xl border bg-card p-4"
            >
                <div class="mb-2 data-label">Personal bests</div>
                <ul class="space-y-1">
                    <li
                        v-for="record in profile.records"
                        :key="record.distance_m"
                        class="flex items-center justify-between text-sm"
                    >
                        <span class="font-medium">{{
                            distanceLabel(record.distance_m)
                        }}</span>
                        <span class="font-bold tabular-nums">{{
                            record.time
                        }}</span>
                    </li>
                </ul>
            </div>

            <p class="text-center text-xs text-muted-foreground">
                Powered by Tempo
            </p>
        </div>
    </div>
</template>
