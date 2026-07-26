<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import {
    index as goalsIndex,
    store as goalsStore,
    destroy,
} from '@/routes/goals';

interface Progress {
    status: string;
    current: number | null;
    target: number;
    projected: number | null;
    gap: number | null;
    unit: string;
    target_date: string;
    days_left: number;
}

interface Goal {
    id: number;
    type: string;
    type_label: string;
    target_value: number;
    distance_m: number | null;
    progress: Progress;
}

const props = defineProps<{
    goals: Goal[];
    typeOptions: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Goals', href: goalsIndex() }],
    },
});

const form = useForm({
    type: 'ctl',
    target_value: '',
    distance_m: '',
    target_date: '',
});

const isRace = computed(() => form.type === 'race_time');

function submit(): void {
    form.post(goalsStore().url, {
        preserveScroll: true,
        onSuccess: () =>
            form.reset('target_value', 'distance_m', 'target_date'),
    });
}

function remove(id: number): void {
    router.delete(destroy(id).url, { preserveScroll: true });
}

const statusStyle: Record<string, { label: string; class: string }> = {
    ahead: { label: 'Ahead', class: 'bg-primary/15 text-primary' },
    on_track: {
        label: 'On track',
        class: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    },
    behind: {
        label: 'Behind',
        class: 'bg-red-500/15 text-red-600 dark:text-red-400',
    },
    unknown: { label: 'No data', class: 'bg-muted text-muted-foreground' },
};

function status(goal: Goal): { label: string; class: string } {
    return statusStyle[goal.progress.status] ?? statusStyle.unknown;
}

function raceTime(seconds: number): string {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.round(seconds % 60);
    const mm = String(m).padStart(2, '0');
    const ss = String(s).padStart(2, '0');

    return h > 0 ? `${h}:${mm}:${ss}` : `${m}:${ss}`;
}

function currentLabel(goal: Goal): string {
    const p = goal.progress;

    if (p.current === null) {
        return 'n/a';
    }

    return p.unit === 'seconds' ? raceTime(p.current) : `${p.current} CTL`;
}

function targetLabel(goal: Goal): string {
    return goal.progress.unit === 'seconds'
        ? raceTime(goal.progress.target)
        : `${goal.progress.target} CTL`;
}

function distanceLabel(m: number): string {
    return m >= 1000
        ? `${(m / 1000).toFixed(m % 1000 === 0 ? 0 : 2)}K`
        : `${m}m`;
}
</script>

<template>
    <Head title="Goals" />

    <div class="px-4 py-6">
        <Heading title="Goals" description="Track fitness and race targets" />

        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
            <div class="space-y-3">
                <p
                    v-if="!props.goals.length"
                    class="rounded-xl border bg-card p-8 text-center text-sm text-muted-foreground"
                >
                    No goals yet. Add one to see whether you are on track.
                </p>

                <section
                    v-for="goal in props.goals"
                    :key="goal.id"
                    class="rounded-xl border bg-card p-5"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-bold">
                                {{ goal.type_label
                                }}<span
                                    v-if="goal.distance_m"
                                    class="text-muted-foreground"
                                >
                                    · {{ distanceLabel(goal.distance_m) }}</span
                                >
                            </h2>
                            <p
                                class="mt-0.5 font-mono text-[11px] text-muted-foreground"
                            >
                                Target {{ targetLabel(goal) }} by
                                {{ goal.progress.target_date }} ·
                                {{ goal.progress.days_left }} days left
                            </p>
                        </div>
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="status(goal).class"
                        >
                            {{ status(goal).label }}
                        </span>
                    </div>

                    <div
                        class="mt-4 flex items-baseline gap-6 text-sm tabular-nums"
                    >
                        <div>
                            <span class="text-muted-foreground">Now </span>
                            <span class="font-semibold">{{
                                currentLabel(goal)
                            }}</span>
                        </div>
                        <div v-if="goal.progress.projected !== null">
                            <span class="text-muted-foreground"
                                >Projected
                            </span>
                            <span class="font-semibold">{{
                                goal.progress.unit === 'seconds'
                                    ? raceTime(goal.progress.projected)
                                    : `${goal.progress.projected} CTL`
                            }}</span>
                        </div>
                        <button
                            class="ml-auto text-xs text-muted-foreground hover:text-red-500"
                            @click="remove(goal.id)"
                        >
                            Remove
                        </button>
                    </div>
                </section>
            </div>

            <form
                class="h-fit space-y-4 rounded-xl border bg-card p-5"
                @submit.prevent="submit"
            >
                <h2 class="text-sm font-bold">Add a goal</h2>

                <div>
                    <label class="text-xs font-medium text-muted-foreground"
                        >Type</label
                    >
                    <select
                        v-model="form.type"
                        class="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                    >
                        <option
                            v-for="opt in props.typeOptions"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                </div>

                <div v-if="isRace">
                    <label class="text-xs font-medium text-muted-foreground"
                        >Distance (m)</label
                    >
                    <input
                        v-model="form.distance_m"
                        type="number"
                        placeholder="21097"
                        class="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <p
                        v-if="form.errors.distance_m"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ form.errors.distance_m }}
                    </p>
                </div>

                <div>
                    <label class="text-xs font-medium text-muted-foreground">{{
                        isRace ? 'Target time (seconds)' : 'Target CTL'
                    }}</label>
                    <input
                        v-model="form.target_value"
                        type="number"
                        step="any"
                        class="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <p
                        v-if="form.errors.target_value"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ form.errors.target_value }}
                    </p>
                </div>

                <div>
                    <label class="text-xs font-medium text-muted-foreground"
                        >Target date</label
                    >
                    <input
                        v-model="form.target_date"
                        type="date"
                        class="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <p
                        v-if="form.errors.target_date"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ form.errors.target_date }}
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-md bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                >
                    Add goal
                </button>
            </form>
        </div>
    </div>
</template>
