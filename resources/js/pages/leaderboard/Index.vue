<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { show } from '@/routes/activities';
import { index as leaderboardIndex } from '@/routes/leaderboard';

interface Effort {
    activity_id: number;
    date: string;
    duration_s: number | null;
    rank: number;
    is_best: boolean;
}

interface Board {
    route_key: string;
    name: string;
    sport: string;
    distance_m: number | null;
    count: number;
    efforts: Effort[];
}

defineProps<{
    boards: Board[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Leaderboard', href: leaderboardIndex() }],
    },
});

function clock(total: number | null): string {
    if (total === null) {
        return '-';
    }

    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = Math.round(total % 60);
    const mm = String(m).padStart(2, '0');
    const ss = String(s).padStart(2, '0');

    return h > 0 ? `${h}:${mm}:${ss}` : `${m}:${ss}`;
}

function km(m: number | null): string {
    return m === null ? '' : `${(m / 1000).toFixed(1)} km`;
}
</script>

<template>
    <Head title="Leaderboard" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <Heading
            title="Route leaderboard"
            description="Your best efforts on routes you repeat"
        />

        <p
            v-if="!boards.length"
            class="rounded-xl border bg-card p-10 text-center text-sm text-muted-foreground"
        >
            No repeated routes yet. Run the same loop twice and it shows up
            here.
        </p>

        <section
            v-for="board in boards"
            :key="board.route_key"
            class="rounded-xl border bg-card p-5"
        >
            <div class="mb-3 flex items-baseline justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold">{{ board.name }}</h2>
                    <p
                        class="mt-0.5 font-mono text-[11px] text-muted-foreground capitalize"
                    >
                        {{ board.sport }} · {{ km(board.distance_m) }} ·
                        {{ board.count }} efforts
                    </p>
                </div>
            </div>

            <ol class="space-y-1">
                <li
                    v-for="effort in board.efforts"
                    :key="effort.activity_id"
                    class="flex items-center gap-3 rounded-lg border bg-background px-3 py-2 text-sm"
                    :class="effort.is_best ? 'border-primary/40' : ''"
                >
                    <span
                        class="w-6 text-center font-bold tabular-nums"
                        :class="
                            effort.is_best
                                ? 'text-primary'
                                : 'text-muted-foreground'
                        "
                        >{{ effort.rank }}</span
                    >
                    <Link
                        :href="show(effort.activity_id)"
                        class="font-semibold tabular-nums hover:underline"
                    >
                        {{ clock(effort.duration_s) }}
                    </Link>
                    <span class="text-xs text-muted-foreground">{{
                        effort.date
                    }}</span>
                    <span
                        v-if="effort.is_best"
                        class="ml-auto rounded bg-primary/15 px-1.5 py-0.5 text-[10px] font-semibold text-primary"
                        >PB</span
                    >
                </li>
            </ol>
        </section>
    </div>
</template>
