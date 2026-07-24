<script setup lang="ts">
import { Form, Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PlanController from '@/actions/App/Http/Controllers/PlanController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as planIndex } from '@/routes/plan';
import type {
    FormStep,
    IntensityOption,
    PlannedWorkout,
    WorkoutStep,
    WorkoutTypeOption,
} from '@/types/plan';

const props = defineProps<{
    workouts: PlannedWorkout[];
    chronosConfigured: boolean;
    intensityOptions: IntensityOption[];
    workoutTypeOptions: WorkoutTypeOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Plan', href: planIndex() }],
    },
});

const page = usePage();
const status = computed(() => page.props.status as string | undefined);
const pushError = computed(
    () => (page.props.errors as Record<string, string>)?.push,
);

const chipClasses: Record<string, string> = {
    slate: 'bg-slate-500/15 text-slate-600 dark:text-slate-300',
    sky: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
    emerald: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    amber: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    red: 'bg-red-500/15 text-red-600 dark:text-red-400',
};

const defaultIntensity = props.intensityOptions[1]?.value ?? 'easy';

const form = useForm({
    date: '',
    sport: 'run',
    workout_type: '',
    title: '',
    notes: '',
    duration_min: '' as number | string,
    steps: [] as FormStep[],
});

const totalDuration = computed(() =>
    form.steps.reduce(
        (sum, step) =>
            sum +
            (Number(step.repeat) || 0) *
                ((Number(step.duration_min) || 0) +
                    (Number(step.recovery_min) || 0)),
        0,
    ),
);

function addStep(): void {
    form.steps.push({
        repeat: 1,
        duration_min: 10,
        intensity: defaultIntensity,
        recovery_min: '',
        recovery_intensity: '',
        label: '',
    });
}

function removeStep(index: number): void {
    form.steps.splice(index, 1);
}

function optional(value: number | string): number | null {
    return value === '' || value === null ? null : Number(value);
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        duration_min: optional(data.duration_min),
        steps: data.steps.map((step) => ({
            repeat: Number(step.repeat) || 1,
            duration_min: Number(step.duration_min) || 0,
            intensity: step.intensity,
            recovery_min: optional(step.recovery_min),
            recovery_intensity: step.recovery_intensity || null,
            label: step.label || null,
        })),
    })).post(PlanController.store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function sportLabel(sport: string): string {
    return sport.charAt(0).toUpperCase() + sport.slice(1);
}

function chip(color: string): string {
    return chipClasses[color] ?? chipClasses.slate;
}

function stepLine(step: WorkoutStep): string {
    const base = `${step.duration_min} min`;

    return step.repeat > 1 ? `${step.repeat} × ${base}` : base;
}
</script>

<template>
    <Head title="Plan" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <Heading
            variant="small"
            title="Plan"
            description="Plan workouts and push them to your calendar."
        />

        <div
            v-if="status"
            class="rounded-md bg-muted px-4 py-3 text-sm text-muted-foreground"
        >
            {{ status }}
        </div>
        <div v-if="pushError" class="text-sm text-destructive">
            {{ pushError }}
        </div>

        <!-- Intensity legend -->
        <Card>
            <CardHeader>
                <CardTitle>Intensity guide</CardTitle>
                <CardDescription>
                    Effort is scored by heart rate. Higher zone means harder.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap gap-2">
                    <div
                        v-for="option in intensityOptions"
                        :key="option.value"
                        class="flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm"
                    >
                        <span
                            class="rounded-md px-2 py-0.5 text-xs font-semibold"
                            :class="chip(option.color)"
                        >
                            Z{{ option.zone }} {{ option.label }}
                        </span>
                        <span class="text-muted-foreground">
                            {{ option.hr_percent }} max HR, {{ option.feel }}
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Add a workout -->
        <Card>
            <CardHeader>
                <CardTitle>Add a workout</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="date">Date</Label>
                            <Input
                                id="date"
                                v-model="form.date"
                                type="date"
                                required
                            />
                            <InputError :message="form.errors.date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="sport">Sport</Label>
                            <select
                                id="sport"
                                v-model="form.sport"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="run">Run</option>
                                <option value="bike">Bike</option>
                                <option value="other">Other</option>
                            </select>
                            <InputError :message="form.errors.sport" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="title">Title</Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                placeholder="Interval session"
                                required
                            />
                            <InputError :message="form.errors.title" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="workout_type">Type</Label>
                            <select
                                id="workout_type"
                                v-model="form.workout_type"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">None</option>
                                <option
                                    v-for="type in workoutTypeOptions"
                                    :key="type.value"
                                    :value="type.value"
                                >
                                    {{ type.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.workout_type" />
                        </div>
                    </div>

                    <!-- Step builder -->
                    <div class="grid gap-3">
                        <div class="flex items-center justify-between">
                            <Label>Steps</Label>
                            <span
                                v-if="form.steps.length > 0"
                                class="text-xs text-muted-foreground"
                            >
                                Total {{ totalDuration }} min
                            </span>
                        </div>

                        <p
                            v-if="form.steps.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No steps yet. Add steps to describe the session, or
                            leave empty for a simple workout and set a duration
                            below.
                        </p>

                        <div
                            v-for="(step, index) in form.steps"
                            :key="index"
                            class="grid gap-3 rounded-lg border p-3 sm:grid-cols-[auto_auto_1fr_auto_auto_auto]"
                        >
                            <div class="grid gap-1">
                                <Label class="text-xs">Repeat</Label>
                                <Input
                                    v-model.number="step.repeat"
                                    type="number"
                                    min="1"
                                    class="w-20"
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-xs">Minutes</Label>
                                <Input
                                    v-model.number="step.duration_min"
                                    type="number"
                                    min="1"
                                    class="w-24"
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-xs">Intensity</Label>
                                <select
                                    v-model="step.intensity"
                                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option
                                        v-for="option in intensityOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }} (Z{{ option.zone }},
                                        {{ option.hr_percent }})
                                    </option>
                                </select>
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-xs">Recovery min</Label>
                                <Input
                                    v-model.number="step.recovery_min"
                                    type="number"
                                    min="0"
                                    class="w-24"
                                    placeholder="0"
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-xs">Recovery at</Label>
                                <select
                                    v-model="step.recovery_intensity"
                                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option value="">Same</option>
                                    <option
                                        v-for="option in intensityOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="removeStep(index)"
                                >
                                    Remove
                                </Button>
                            </div>
                        </div>

                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="addStep"
                            >
                                Add step
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="duration_min">
                                Duration (min, if no steps)
                            </Label>
                            <Input
                                id="duration_min"
                                v-model="form.duration_min"
                                type="number"
                                :disabled="form.steps.length > 0"
                                :placeholder="
                                    form.steps.length > 0
                                        ? String(totalDuration)
                                        : ''
                                "
                            />
                            <InputError :message="form.errors.duration_min" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="notes">Notes (optional)</Label>
                            <Input
                                id="notes"
                                v-model="form.notes"
                                placeholder="Anything extra"
                            />
                            <InputError :message="form.errors.notes" />
                        </div>
                    </div>

                    <div>
                        <Button type="submit" :disabled="form.processing">
                            Add workout
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Planned workouts -->
        <Card>
            <CardHeader>
                <CardTitle>Planned workouts</CardTitle>
                <CardDescription v-if="!chronosConfigured">
                    Set CHRONOS_API_URL and CHRONOS_API_TOKEN to push workouts
                    to your calendar.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p
                    v-if="workouts.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Nothing planned yet.
                </p>
                <ul v-else class="divide-y">
                    <li
                        v-for="workout in workouts"
                        :key="workout.id"
                        class="flex flex-wrap items-start justify-between gap-3 py-4"
                    >
                        <div class="min-w-0 space-y-2 text-sm">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">
                                    {{ workout.date }}
                                </span>
                                <span class="text-muted-foreground">
                                    {{ sportLabel(workout.sport) }}:
                                    {{ workout.title }}
                                </span>
                                <span
                                    v-if="workout.workout_type"
                                    class="rounded-md bg-muted px-2 py-0.5 text-xs font-medium"
                                >
                                    {{ workout.workout_type.label }}
                                </span>
                                <span
                                    v-if="workout.duration_min"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ workout.duration_min }} min
                                </span>
                            </div>

                            <ul
                                v-if="workout.steps.length > 0"
                                class="space-y-1"
                            >
                                <li
                                    v-for="step in workout.steps"
                                    :key="step.position"
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <span
                                        v-if="step.label"
                                        class="text-muted-foreground"
                                    >
                                        {{ step.label }}:
                                    </span>
                                    <span class="font-medium">
                                        {{ stepLine(step) }}
                                    </span>
                                    <span
                                        class="rounded-md px-2 py-0.5 text-xs font-semibold"
                                        :class="chip(step.intensity.color)"
                                    >
                                        {{ step.intensity.label }} (Z{{
                                            step.intensity.zone
                                        }}
                                        · {{ step.intensity.hr_percent }} max
                                        HR)
                                    </span>
                                    <span
                                        v-if="step.recovery_min"
                                        class="text-xs text-muted-foreground"
                                    >
                                        +{{ step.recovery_min }} min
                                        {{
                                            (
                                                step.recovery_intensity ??
                                                step.intensity
                                            ).label.toLowerCase()
                                        }}
                                        between
                                    </span>
                                </li>
                            </ul>

                            <div
                                v-else-if="workout.notes"
                                class="text-muted-foreground"
                            >
                                {{ workout.notes }}
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <a
                                v-if="workout.pushed && workout.chronos_url"
                                :href="workout.chronos_url"
                                target="_blank"
                                rel="noopener"
                                class="text-sm text-emerald-600 hover:underline dark:text-emerald-400"
                            >
                                In calendar
                            </a>
                            <Form
                                v-else
                                v-bind="PlanController.push.form(workout.id)"
                                v-slot="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    size="sm"
                                    :disabled="processing || !chronosConfigured"
                                >
                                    Push to calendar
                                </Button>
                            </Form>
                            <Form
                                v-bind="PlanController.destroy.form(workout.id)"
                                v-slot="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="outline"
                                    :disabled="processing"
                                >
                                    Remove
                                </Button>
                            </Form>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
