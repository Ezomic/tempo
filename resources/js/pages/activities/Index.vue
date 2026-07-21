<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { index as activitiesIndex, show } from '@/routes/activities';

interface ActivityRow {
    id: number;
    sport: string;
    started_at: string;
    distance_m: number | null;
    duration_s: number | null;
    avg_hr: number | null;
    trimp: number | null;
}

interface Paginator {
    data: ActivityRow[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

defineProps<{ activities: Paginator }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Activities', href: activitiesIndex() }],
    },
});

function sportLabel(sport: string): string {
    return sport.charAt(0).toUpperCase() + sport.slice(1);
}

function distance(m: number | null): string {
    return m === null ? '—' : `${(m / 1000).toFixed(1)} km`;
}

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

function day(iso: string): string {
    return new Date(iso).toLocaleDateString();
}
</script>

<template>
    <Head title="Activities" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <Heading
            variant="small"
            title="Activities"
            description="Your synced runs and rides."
        />

        <p
            v-if="activities.data.length === 0"
            class="text-sm text-muted-foreground"
        >
            No activities yet. Connect Garmin and sync to see them here.
        </p>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Date</th>
                        <th class="px-4 py-2 font-medium">Sport</th>
                        <th class="px-4 py-2 font-medium">Distance</th>
                        <th class="px-4 py-2 font-medium">Duration</th>
                        <th class="px-4 py-2 font-medium">Avg HR</th>
                        <th class="px-4 py-2 font-medium">Load</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="activity in activities.data"
                        :key="activity.id"
                        class="border-t hover:bg-muted/40"
                    >
                        <td class="px-4 py-2">
                            <Link
                                :href="show(activity.id)"
                                class="font-medium hover:underline"
                            >
                                {{ day(activity.started_at) }}
                            </Link>
                        </td>
                        <td class="px-4 py-2">
                            {{ sportLabel(activity.sport) }}
                        </td>
                        <td class="px-4 py-2">
                            {{ distance(activity.distance_m) }}
                        </td>
                        <td class="px-4 py-2">
                            {{ duration(activity.duration_s) }}
                        </td>
                        <td class="px-4 py-2">{{ activity.avg_hr ?? '—' }}</td>
                        <td class="px-4 py-2">{{ activity.trimp ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="activities.last_page > 1"
            class="flex items-center justify-between text-sm"
        >
            <Link
                v-if="activities.prev_page_url"
                :href="activities.prev_page_url"
                class="rounded-md border px-3 py-1.5 hover:bg-muted"
            >
                Previous
            </Link>
            <span v-else class="text-muted-foreground">Previous</span>

            <span class="text-muted-foreground">
                Page {{ activities.current_page }} of {{ activities.last_page }}
            </span>

            <Link
                v-if="activities.next_page_url"
                :href="activities.next_page_url"
                class="rounded-md border px-3 py-1.5 hover:bg-muted"
            >
                Next
            </Link>
            <span v-else class="text-muted-foreground">Next</span>
        </div>
    </div>
</template>
