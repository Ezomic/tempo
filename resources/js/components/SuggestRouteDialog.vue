<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import RouteController from '@/actions/App/Http/Controllers/RouteController';
import RouteMap from '@/components/RouteMap.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { postJson } from '@/lib/http';

interface RouteResult {
    coordinates: [number, number][];
    distance_m: number;
    ascent_m: number;
    kind: string;
    mode: string;
    message?: string;
}

const props = defineProps<{
    workoutId: number;
    workoutType: string | null;
    triggerLabel: string;
}>();

const open = ref(false);
const loading = ref(false);
const error = ref<string | null>(null);
const route = ref<RouteResult | null>(null);
const mode = ref<string>(
    props.workoutType === 'intervals' ? 'intervals' : 'loop',
);

async function generate(): Promise<void> {
    loading.value = true;
    error.value = null;
    const seed = Math.floor(Math.random() * 1_000_000);
    const { ok, data } = await postJson<RouteResult>(
        RouteController.suggest.url(props.workoutId),
        { mode: mode.value, seed },
    );
    loading.value = false;

    if (ok) {
        route.value = data;
    } else {
        error.value = data.message ?? 'Could not generate a route.';
    }
}

function setMode(value: string): void {
    if (mode.value === value) {
        return;
    }

    mode.value = value;
    generate();
}

function onOpenChange(value: boolean): void {
    open.value = value;

    if (value && route.value === null) {
        generate();
    }
}

async function save(): Promise<void> {
    if (route.value === null) {
        return;
    }

    loading.value = true;
    const { ok, data } = await postJson<{ message?: string }>(
        RouteController.save.url(props.workoutId),
        {
            coordinates: route.value.coordinates,
            distance_m: route.value.distance_m,
            ascent_m: route.value.ascent_m,
            kind: route.value.kind,
        },
    );
    loading.value = false;

    if (ok) {
        open.value = false;
        router.reload({ only: ['workouts'] });
    } else {
        error.value = data.message ?? 'Could not save the route.';
    }
}

function km(meters: number): string {
    return (meters / 1000).toFixed(1);
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogTrigger as-child>
            <Button type="button" size="sm" variant="outline">
                {{ triggerLabel }}
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Suggested route</DialogTitle>
                <DialogDescription>
                    {{
                        mode === 'intervals'
                            ? 'A flat out-and-back so your reps land on even ground.'
                            : 'A fresh loop from home, sized to this workout.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    :variant="mode === 'loop' ? 'default' : 'outline'"
                    :disabled="loading"
                    @click="setMode('loop')"
                >
                    Loop
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :variant="mode === 'intervals' ? 'default' : 'outline'"
                    :disabled="loading"
                    @click="setMode('intervals')"
                >
                    Flat reps
                </Button>
            </div>

            <div v-if="error" class="text-sm text-destructive">{{ error }}</div>

            <RouteMap
                v-if="route"
                :coordinates="route.coordinates"
                height-class="h-72"
            />
            <div
                v-else
                class="flex h-72 items-center justify-center rounded-lg border text-sm text-muted-foreground"
            >
                {{ loading ? 'Generating…' : 'No route yet.' }}
            </div>

            <div v-if="route" class="flex items-center gap-4 text-sm">
                <span>
                    <span class="font-semibold">{{
                        km(route.distance_m)
                    }}</span>
                    km
                </span>
                <span class="text-muted-foreground">
                    {{ Math.round(route.ascent_m) }} m climb
                </span>
            </div>

            <DialogFooter class="gap-2">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="loading"
                    @click="generate"
                >
                    Regenerate
                </Button>
                <Button
                    type="button"
                    :disabled="loading || route === null"
                    @click="save"
                >
                    Save to workout
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
