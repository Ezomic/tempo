<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { index as planIndex, generate } from '@/routes/plan';

interface SportOption {
    value: string;
    label: string;
}

defineProps<{ sports: SportOption[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Plan', href: planIndex() }],
    },
});

const form = useForm({
    race_date: '',
    sport: 'run',
    sessions_per_week: 4,
});

function submit(): void {
    form.post(generate().url);
}
</script>

<template>
    <Head title="Generate a plan" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <Heading
            title="Generate a training plan"
            description="A periodized build to your race, based on your current fitness"
        />

        <form
            class="max-w-md space-y-5 rounded-xl border bg-card p-5"
            @submit.prevent="submit"
        >
            <div>
                <label class="mb-1.5 block text-sm font-medium"
                    >Race date</label
                >
                <input
                    v-model="form.race_date"
                    type="date"
                    required
                    class="h-10 w-full rounded-lg border bg-background px-3 text-sm"
                />
                <p
                    v-if="form.errors.race_date"
                    class="mt-1 text-xs text-red-500"
                >
                    {{ form.errors.race_date }}
                </p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium"
                    >Discipline</label
                >
                <select
                    v-model="form.sport"
                    class="h-10 w-full rounded-lg border bg-background px-3 text-sm"
                >
                    <option
                        v-for="sport in sports"
                        :key="sport.value"
                        :value="sport.value"
                    >
                        {{ sport.label }}
                    </option>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium"
                    >Sessions per week</label
                >
                <select
                    v-model.number="form.sessions_per_week"
                    class="h-10 w-full rounded-lg border bg-background px-3 text-sm"
                >
                    <option v-for="n in [3, 4, 5, 6]" :key="n" :value="n">
                        {{ n }}
                    </option>
                </select>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex h-10 items-center rounded-lg bg-primary px-5 text-sm font-semibold text-primary-foreground disabled:opacity-60"
            >
                Generate plan
            </button>
            <p class="text-xs text-muted-foreground">
                This replaces any previously generated sessions but keeps
                workouts you added by hand.
            </p>
        </form>
    </div>
</template>
