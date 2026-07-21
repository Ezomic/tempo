<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
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

interface Workout {
    id: number;
    date: string;
    sport: string;
    title: string;
    notes: string | null;
    duration_min: number | null;
    pushed: boolean;
    chronos_url: string | null;
}

defineProps<{ workouts: Workout[]; chronosConfigured: boolean }>();

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

function sportLabel(sport: string): string {
    return sport.charAt(0).toUpperCase() + sport.slice(1);
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

        <Card>
            <CardHeader>
                <CardTitle>Add a workout</CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="PlanController.store.form()"
                    :reset-on-success="true"
                    class="grid gap-4 sm:grid-cols-2"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="date">Date</Label>
                        <Input id="date" name="date" type="date" required />
                        <InputError :message="errors.date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="sport">Sport</Label>
                        <select
                            id="sport"
                            name="sport"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="run">Run</option>
                            <option value="bike">Bike</option>
                            <option value="other">Other</option>
                        </select>
                        <InputError :message="errors.sport" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="title">Title</Label>
                        <Input
                            id="title"
                            name="title"
                            placeholder="Easy 40 min"
                            required
                        />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="duration_min">Duration (min)</Label>
                        <Input
                            id="duration_min"
                            name="duration_min"
                            type="number"
                        />
                        <InputError :message="errors.duration_min" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="notes">Notes</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="2"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="errors.notes" />
                    </div>
                    <div class="sm:col-span-2">
                        <Button type="submit" :disabled="processing">
                            Add
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>

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
                        class="flex flex-wrap items-center justify-between gap-3 py-3"
                    >
                        <div class="text-sm">
                            <div class="font-medium">
                                {{ workout.date }} —
                                {{ sportLabel(workout.sport) }}:
                                {{ workout.title }}
                                <span
                                    v-if="workout.duration_min"
                                    class="text-muted-foreground"
                                >
                                    ({{ workout.duration_min }} min)
                                </span>
                            </div>
                            <div
                                v-if="workout.notes"
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
