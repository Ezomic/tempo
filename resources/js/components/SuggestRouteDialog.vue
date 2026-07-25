<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import RouteController from '@/actions/App/Http/Controllers/RouteController';
import RouteMap from '@/components/RouteMap.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    sport: string;
    triggerLabel: string;
}>();

const open = ref(false);
const loading = ref(false);
const error = ref<string | null>(null);
const route = ref<RouteResult | null>(null);
const mode = ref<string>(
    props.workoutType === 'intervals' ? 'intervals' : 'loop',
);
const distanceKm = ref<number | string>('');
const preferTrails = ref(false);

async function generate(): Promise<void> {
    loading.value = true;
    error.value = null;
    const seed = Math.floor(Math.random() * 1_000_000);
    const { ok, data } = await postJson<RouteResult>(
        RouteController.suggest.url(props.workoutId),
        {
            mode: mode.value,
            seed,
            prefer_trails: preferTrails.value,
            distance_m:
                distanceKm.value !== '' && Number(distanceKm.value) > 0
                    ? Math.round(Number(distanceKm.value) * 1000)
                    : undefined,
        },
    );
    loading.value = false;

    if (ok) {
        route.value = data;

        // Seed the distance control from the first suggestion, then leave it
        // under the user's control.
        if (distanceKm.value === '') {
            distanceKm.value = Math.round(data.distance_m / 100) / 10;
        }
    } else {
        error.value = data.message ?? 'Could not generate a route.';
    }
}

function setMode(value: string): void {
    mode.value = value;
}

function onOpenChange(value: boolean): void {
    open.value = value;

    // Start fresh; wait for the user to set their options and hit Generate.
    if (value) {
        route.value = null;
        error.value = null;
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

            <div class="flex flex-wrap items-end gap-4">
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

                <div class="grid gap-1">
                    <Label for="distance" class="text-xs">Distance (km)</Label>
                    <Input
                        id="distance"
                        v-model.number="distanceKm"
                        type="number"
                        min="0.5"
                        step="0.5"
                        placeholder="auto"
                        class="w-24"
                    />
                </div>

                <label
                    v-if="sport === 'run'"
                    class="flex items-center gap-2 text-sm"
                >
                    <Checkbox
                        :model-value="preferTrails"
                        @update:model-value="(v) => (preferTrails = v === true)"
                    />
                    Prefer trails
                </label>
                <span
                    v-else-if="sport === 'bike'"
                    class="text-xs text-muted-foreground"
                >
                    Mountain-bike route (tracks &amp; trails)
                </span>
            </div>

            <div v-if="error" class="text-sm text-destructive">{{ error }}</div>

            <RouteMap
                v-if="route"
                :coordinates="route.coordinates"
                height-class="h-72"
            />
            <div
                v-else
                class="flex h-72 items-center justify-center rounded-lg border px-6 text-center text-sm text-muted-foreground"
            >
                {{
                    loading
                        ? 'Generating…'
                        : 'Set your distance and options, then generate a route.'
                }}
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
                    v-if="route === null"
                    type="button"
                    :disabled="loading"
                    @click="generate"
                >
                    Generate route
                </Button>
                <template v-else>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="loading"
                        @click="generate"
                    >
                        Regenerate
                    </Button>
                    <Button type="button" :disabled="loading" @click="save">
                        Save to workout
                    </Button>
                </template>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
