<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import {
    index as workoutsIndex,
    store as workoutsStore,
    destroy as workoutsDestroy,
    apply as workoutsApply,
} from '@/routes/workouts';

interface Step {
    repeat: number;
    intensity: string;
    duration_min: number;
    recovery_min: number | null;
    recovery_intensity: string | null;
    label: string | null;
}

interface Template {
    id: number;
    name: string;
    sport: string;
    workout_type: string | null;
    steps: Step[];
}

interface Option {
    value: string;
    label: string;
}

const props = defineProps<{
    templates: Template[];
    sports: Option[];
    intensityOptions: Option[];
    workoutTypeOptions: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Workouts', href: workoutsIndex() }],
    },
});

function blankStep(): Step {
    return {
        repeat: 1,
        intensity: props.intensityOptions[0]?.value ?? 'easy',
        duration_min: 5,
        recovery_min: null,
        recovery_intensity: null,
        label: null,
    };
}

const form = useForm<{
    name: string;
    sport: string;
    workout_type: string | null;
    steps: Step[];
}>({
    name: '',
    sport: props.sports[0]?.value ?? 'run',
    workout_type: null,
    steps: [blankStep()],
});

function addStep(): void {
    form.steps.push(blankStep());
}

function removeStep(index: number): void {
    if (form.steps.length > 1) {
        form.steps.splice(index, 1);
    }
}

function save(): void {
    form.post(workoutsStore().url, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.steps = [blankStep()];
        },
    });
}

const applyDates = reactive<Record<number, string>>({});

function applyTemplate(id: number): void {
    const date = applyDates[id];

    if (!date) {
        return;
    }

    router.post(workoutsApply(id).url, { date }, { preserveScroll: true });
}

function removeTemplate(id: number): void {
    router.delete(workoutsDestroy(id).url, { preserveScroll: true });
}

function stepSummary(step: Step): string {
    const label =
        props.intensityOptions.find((o) => o.value === step.intensity)?.label ??
        step.intensity;
    const base = `${step.repeat}x ${step.duration_min}min ${label}`;

    return step.recovery_min ? `${base} / ${step.recovery_min}min rec` : base;
}
</script>

<template>
    <Head title="Workouts" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <Heading
            title="Workout library"
            description="Build reusable structured sessions and drop them onto your plan"
        />

        <div class="grid gap-6 lg:grid-cols-[380px_1fr]">
            <!-- Builder -->
            <form
                class="h-fit space-y-4 rounded-xl border bg-card p-5"
                @submit.prevent="save"
            >
                <h2 class="text-sm font-bold">New template</h2>

                <div>
                    <label class="text-xs font-medium text-muted-foreground"
                        >Name</label
                    >
                    <input
                        v-model="form.name"
                        type="text"
                        placeholder="e.g. 5x1000m threshold"
                        class="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <p
                        v-if="form.errors.name"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <div class="flex-1">
                        <label class="text-xs font-medium text-muted-foreground"
                            >Sport</label
                        >
                        <select
                            v-model="form.sport"
                            class="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            <option
                                v-for="s in sports"
                                :key="s.value"
                                :value="s.value"
                            >
                                {{ s.label }}
                            </option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="text-xs font-medium text-muted-foreground"
                            >Type</label
                        >
                        <select
                            v-model="form.workout_type"
                            class="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            <option :value="null">None</option>
                            <option
                                v-for="t in workoutTypeOptions"
                                :key="t.value"
                                :value="t.value"
                            >
                                {{ t.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-muted-foreground"
                            >Steps</span
                        >
                        <button
                            type="button"
                            class="text-xs font-semibold text-primary"
                            @click="addStep"
                        >
                            + Add step
                        </button>
                    </div>

                    <div
                        v-for="(step, index) in form.steps"
                        :key="index"
                        class="space-y-2 rounded-lg border bg-background p-3"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold"
                                >Step {{ index + 1 }}</span
                            >
                            <button
                                v-if="form.steps.length > 1"
                                type="button"
                                class="text-xs text-muted-foreground hover:text-red-500"
                                @click="removeStep(index)"
                            >
                                Remove
                            </button>
                        </div>
                        <div class="flex gap-2">
                            <label class="flex-1 text-xs">
                                Reps
                                <input
                                    v-model.number="step.repeat"
                                    type="number"
                                    min="1"
                                    class="mt-1 w-full rounded-md border bg-card px-2 py-1.5 text-sm"
                                />
                            </label>
                            <label class="flex-1 text-xs">
                                Min
                                <input
                                    v-model.number="step.duration_min"
                                    type="number"
                                    min="1"
                                    class="mt-1 w-full rounded-md border bg-card px-2 py-1.5 text-sm"
                                />
                            </label>
                        </div>
                        <label class="block text-xs">
                            Intensity
                            <select
                                v-model="step.intensity"
                                class="mt-1 w-full rounded-md border bg-card px-2 py-1.5 text-sm"
                            >
                                <option
                                    v-for="i in intensityOptions"
                                    :key="i.value"
                                    :value="i.value"
                                >
                                    {{ i.label }}
                                </option>
                            </select>
                        </label>
                        <div class="flex gap-2">
                            <label class="flex-1 text-xs">
                                Recovery min
                                <input
                                    v-model.number="step.recovery_min"
                                    type="number"
                                    min="0"
                                    class="mt-1 w-full rounded-md border bg-card px-2 py-1.5 text-sm"
                                />
                            </label>
                            <label class="flex-1 text-xs">
                                Recovery intensity
                                <select
                                    v-model="step.recovery_intensity"
                                    class="mt-1 w-full rounded-md border bg-card px-2 py-1.5 text-sm"
                                >
                                    <option :value="null">-</option>
                                    <option
                                        v-for="i in intensityOptions"
                                        :key="i.value"
                                        :value="i.value"
                                    >
                                        {{ i.label }}
                                    </option>
                                </select>
                            </label>
                        </div>
                    </div>
                    <p v-if="form.errors.steps" class="text-xs text-red-500">
                        {{ form.errors.steps }}
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-md bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                >
                    Save template
                </button>
            </form>

            <!-- Library -->
            <div class="space-y-3">
                <p
                    v-if="!templates.length"
                    class="rounded-xl border bg-card p-8 text-center text-sm text-muted-foreground"
                >
                    No templates yet. Build one to reuse it on any day.
                </p>

                <section
                    v-for="template in templates"
                    :key="template.id"
                    class="rounded-xl border bg-card p-5"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-bold">
                                {{ template.name }}
                            </h2>
                            <p
                                class="mt-0.5 font-mono text-[11px] text-muted-foreground capitalize"
                            >
                                {{ template.sport
                                }}<span v-if="template.workout_type">
                                    · {{ template.workout_type }}</span
                                >
                            </p>
                        </div>
                        <button
                            class="text-xs text-muted-foreground hover:text-red-500"
                            @click="removeTemplate(template.id)"
                        >
                            Delete
                        </button>
                    </div>

                    <ul
                        class="mt-3 flex flex-wrap gap-2 text-xs text-muted-foreground"
                    >
                        <li
                            v-for="(step, i) in template.steps"
                            :key="i"
                            class="rounded-md border bg-background px-2 py-1"
                        >
                            {{ stepSummary(step) }}
                        </li>
                    </ul>

                    <div class="mt-4 flex items-center gap-2">
                        <input
                            v-model="applyDates[template.id]"
                            type="date"
                            class="rounded-md border bg-background px-3 py-1.5 text-sm"
                        />
                        <button
                            class="rounded-md bg-primary px-3 py-1.5 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                            :disabled="!applyDates[template.id]"
                            @click="applyTemplate(template.id)"
                        >
                            Add to plan
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
