<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { index as pacingIndex, plan as pacingPlan } from '@/routes/pacing';

interface Split {
    index: number;
    distance_m: number;
    avg_grade: number;
    pace_per_km: number;
    cumulative_s: number;
}

interface Plan {
    total_distance_m: number;
    target_seconds: number;
    total_seconds: number;
    weather_factor: number;
    splits: Split[];
    weather: { temp: number | null; wind: number | null; applied: boolean };
}

interface RaceGoal {
    id: number;
    distance_m: number;
    target_date: string;
    predicted_seconds: number | null;
}

const props = defineProps<{
    raceGoals: RaceGoal[];
    plan: Plan | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pacing', href: pacingIndex() }],
    },
});

const file = ref<File | null>(null);
const hours = ref(1);
const minutes = ref(45);
const seconds = ref(0);
const splitKm = ref(1);
const raceDate = ref<string | null>(null);
const errors = ref<Record<string, string>>({});
const processing = ref(false);

const targetSeconds = computed(
    () => hours.value * 3600 + minutes.value * 60 + seconds.value,
);

function onFile(event: Event): void {
    const target = event.target as HTMLInputElement;
    file.value = target.files?.[0] ?? null;
}

function prefill(goal: RaceGoal): void {
    raceDate.value = goal.target_date;

    if (goal.predicted_seconds !== null) {
        hours.value = Math.floor(goal.predicted_seconds / 3600);
        minutes.value = Math.floor((goal.predicted_seconds % 3600) / 60);
        seconds.value = Math.round(goal.predicted_seconds % 60);
    }
}

function submit(): void {
    if (!file.value) {
        errors.value = { gpx: 'Choose a GPX file.' };

        return;
    }

    processing.value = true;
    router.post(
        pacingPlan().url,
        {
            gpx: file.value,
            target_seconds: targetSeconds.value,
            split_km: splitKm.value,
            race_date: raceDate.value,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onError: (e) => (errors.value = e),
            onFinish: () => (processing.value = false),
        },
    );
}

function clock(total: number): string {
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = Math.round(total % 60);
    const mm = String(m).padStart(2, '0');
    const ss = String(s).padStart(2, '0');

    return h > 0 ? `${h}:${mm}:${ss}` : `${m}:${ss}`;
}

function pace(perKm: number): string {
    const m = Math.floor(perKm / 60);
    const s = Math.round(perKm % 60);

    return `${m}:${String(s).padStart(2, '0')}/km`;
}

function goalDistance(m: number): string {
    return `${(m / 1000).toFixed(m % 1000 === 0 ? 0 : 2)} km`;
}
</script>

<template>
    <Head title="Pacing" />

    <div class="px-4 py-6">
        <Heading
            title="Race pacing"
            description="Grade-adjusted splits from a course GPX"
        />

        <div class="mt-6 grid gap-6 lg:grid-cols-[320px_1fr]">
            <form
                class="h-fit space-y-4 rounded-xl border bg-card p-5"
                @submit.prevent="submit"
            >
                <div>
                    <label class="text-xs font-medium text-muted-foreground"
                        >Course GPX</label
                    >
                    <input
                        type="file"
                        accept=".gpx,application/gpx+xml,text/xml"
                        class="mt-1 w-full text-sm"
                        @change="onFile"
                    />
                    <p v-if="errors.gpx" class="mt-1 text-xs text-red-500">
                        {{ errors.gpx }}
                    </p>
                </div>

                <div v-if="props.raceGoals.length">
                    <label class="text-xs font-medium text-muted-foreground"
                        >Prefill from race goal</label
                    >
                    <div class="mt-1 space-y-1">
                        <button
                            v-for="goal in props.raceGoals"
                            :key="goal.id"
                            type="button"
                            class="w-full rounded-md border bg-background px-3 py-1.5 text-left text-xs hover:bg-muted"
                            @click="prefill(goal)"
                        >
                            {{ goalDistance(goal.distance_m) }} ·
                            {{ goal.target_date }}
                            <span
                                v-if="goal.predicted_seconds"
                                class="text-muted-foreground"
                                >· ~{{ clock(goal.predicted_seconds) }}</span
                            >
                        </button>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-medium text-muted-foreground"
                        >Target finish</label
                    >
                    <div class="mt-1 flex gap-2">
                        <input
                            v-model.number="hours"
                            type="number"
                            min="0"
                            class="w-full rounded-md border bg-background px-2 py-2 text-sm"
                            aria-label="hours"
                        />
                        <input
                            v-model.number="minutes"
                            type="number"
                            min="0"
                            max="59"
                            class="w-full rounded-md border bg-background px-2 py-2 text-sm"
                            aria-label="minutes"
                        />
                        <input
                            v-model.number="seconds"
                            type="number"
                            min="0"
                            max="59"
                            class="w-full rounded-md border bg-background px-2 py-2 text-sm"
                            aria-label="seconds"
                        />
                    </div>
                    <p
                        v-if="errors.target_seconds"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ errors.target_seconds }}
                    </p>
                </div>

                <div>
                    <label class="text-xs font-medium text-muted-foreground"
                        >Split</label
                    >
                    <select
                        v-model.number="splitKm"
                        class="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                    >
                        <option :value="0.5">0.5 km</option>
                        <option :value="1">1 km</option>
                        <option :value="2">2 km</option>
                        <option :value="5">5 km</option>
                    </select>
                </div>

                <button
                    type="submit"
                    :disabled="processing"
                    class="w-full rounded-md bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                >
                    Build pacing plan
                </button>
            </form>

            <div>
                <p
                    v-if="!props.plan"
                    class="rounded-xl border bg-card p-8 text-center text-sm text-muted-foreground"
                >
                    Upload a course and set a target time to see split paces.
                </p>

                <section v-else class="rounded-xl border bg-card p-5">
                    <div class="mb-4 flex flex-wrap gap-x-8 gap-y-2 text-sm">
                        <div>
                            <span class="text-muted-foreground">Distance </span>
                            <span class="font-semibold"
                                >{{
                                    (
                                        props.plan.total_distance_m / 1000
                                    ).toFixed(2)
                                }}
                                km</span
                            >
                        </div>
                        <div>
                            <span class="text-muted-foreground">Target </span>
                            <span class="font-semibold">{{
                                clock(props.plan.target_seconds)
                            }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground"
                                >Plan total
                            </span>
                            <span class="font-semibold">{{
                                clock(props.plan.total_seconds)
                            }}</span>
                        </div>
                        <div
                            v-if="props.plan.weather.applied"
                            class="text-amber-600 dark:text-amber-400"
                        >
                            Weather adjusted (+{{
                                Math.round(
                                    (props.plan.weather_factor - 1) * 100,
                                )
                            }}%)
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm tabular-nums">
                            <thead
                                class="text-left font-mono text-[10px] tracking-[0.08em] text-muted-foreground uppercase"
                            >
                                <tr>
                                    <th class="py-1 pr-4">Split</th>
                                    <th class="py-1 pr-4">Grade</th>
                                    <th class="py-1 pr-4">Pace</th>
                                    <th class="py-1">Elapsed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="split in props.plan.splits"
                                    :key="split.index"
                                    class="border-t"
                                >
                                    <td class="py-1.5 pr-4">
                                        {{ split.index }}
                                    </td>
                                    <td
                                        class="py-1.5 pr-4"
                                        :class="
                                            split.avg_grade > 1
                                                ? 'text-red-500'
                                                : split.avg_grade < -1
                                                  ? 'text-emerald-500'
                                                  : 'text-muted-foreground'
                                        "
                                    >
                                        {{ split.avg_grade > 0 ? '+' : ''
                                        }}{{ split.avg_grade }}%
                                    </td>
                                    <td class="py-1.5 pr-4 font-medium">
                                        {{ pace(split.pace_per_km) }}
                                    </td>
                                    <td class="py-1.5">
                                        {{ clock(split.cumulative_s) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
