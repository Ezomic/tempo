<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { index as planIndex, move as planMove } from '@/routes/plan';

interface Workout {
    id: number;
    title: string;
    sport: string;
    workout_type: string | null;
    generated: boolean;
    pushed: boolean;
}

interface Day {
    date: string;
    is_today: boolean;
    is_past: boolean;
    workouts: Workout[];
}

interface Week {
    week_start: string;
    days: Day[];
}

defineProps<{
    weeks: Week[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Plan', href: planIndex() }],
    },
});

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const dragging = ref<{ id: number; from: string } | null>(null);
const dropTarget = ref<string | null>(null);

const typeColor: Record<string, string> = {
    recovery: 'bg-slate-400',
    easy: 'bg-sky-400',
    endurance: 'bg-sky-500',
    tempo: 'bg-amber-500',
    intervals: 'bg-orange-500',
    long: 'bg-indigo-500',
};

function onDragStart(workout: Workout, date: string): void {
    dragging.value = { id: workout.id, from: date };
}

function onDrop(date: string): void {
    dropTarget.value = null;
    const drag = dragging.value;
    dragging.value = null;

    if (!drag || drag.from === date) {
        return;
    }

    router.post(
        planMove(drag.id).url,
        { date },
        { preserveScroll: true, preserveState: false },
    );
}

function dayLabel(date: string): string {
    return String(new Date(date).getDate());
}
</script>

<template>
    <Head title="Plan calendar" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <Heading
                variant="small"
                title="Plan calendar"
                description="Drag a session onto another day to move it."
            />
            <Link
                :href="planIndex()"
                class="rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-muted"
            >
                Back to plan
            </Link>
        </div>

        <div class="overflow-x-auto">
            <div class="min-w-[720px]">
                <div
                    class="grid grid-cols-7 gap-2 pb-1 text-xs font-medium text-muted-foreground"
                >
                    <div v-for="day in WEEKDAYS" :key="day" class="px-1">
                        {{ day }}
                    </div>
                </div>

                <div class="space-y-2">
                    <div
                        v-for="week in weeks"
                        :key="week.week_start"
                        class="grid grid-cols-7 gap-2"
                    >
                        <div
                            v-for="day in week.days"
                            :key="day.date"
                            class="min-h-24 rounded-lg border p-1.5 transition-colors"
                            :class="[
                                day.is_today
                                    ? 'border-primary/50'
                                    : 'border-border',
                                dropTarget === day.date
                                    ? 'bg-primary/10'
                                    : day.is_past
                                      ? 'bg-muted/40'
                                      : 'bg-card',
                            ]"
                            @dragover.prevent="dropTarget = day.date"
                            @dragleave="
                                dropTarget === day.date && (dropTarget = null)
                            "
                            @drop.prevent="onDrop(day.date)"
                        >
                            <div
                                class="mb-1 text-right text-[11px]"
                                :class="
                                    day.is_today
                                        ? 'font-bold text-primary'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ dayLabel(day.date) }}
                            </div>
                            <div
                                v-for="workout in day.workouts"
                                :key="workout.id"
                                draggable="true"
                                class="mb-1 cursor-grab rounded-md border bg-background px-1.5 py-1 text-[11px] leading-tight active:cursor-grabbing"
                                @dragstart="onDragStart(workout, day.date)"
                            >
                                <div class="flex items-center gap-1">
                                    <span
                                        class="h-2 w-2 shrink-0 rounded-full"
                                        :class="
                                            typeColor[
                                                workout.workout_type ?? ''
                                            ] ?? 'bg-slate-400'
                                        "
                                    />
                                    <span class="truncate font-medium">{{
                                        workout.title
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <p class="text-xs text-muted-foreground">
            Moving a session that is on your calendar updates its calendar event
            too.
        </p>
    </div>
</template>
