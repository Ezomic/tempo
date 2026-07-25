<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import HomeLocationController from '@/actions/App/Http/Controllers/Settings/HomeLocationController';
import Heading from '@/components/Heading.vue';
import RouteMap from '@/components/RouteMap.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { postJson } from '@/lib/http';
import { edit, update } from '@/routes/routes';

const props = defineProps<{
    home: { lat: number; lng: number } | null;
    routingConfigured: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Home & routes', href: edit() }],
    },
});

const page = usePage();
const status = computed(() => page.props.status as string | undefined);

const form = useForm({
    home_lat: (props.home?.lat ?? '') as number | string,
    home_lng: (props.home?.lng ?? '') as number | string,
});

const hasHome = computed(
    () =>
        typeof form.home_lat === 'number' && typeof form.home_lng === 'number',
);

const marker = computed<[number, number] | null>(() =>
    hasHome.value ? [form.home_lat as number, form.home_lng as number] : null,
);

function setMarker(value: [number, number]): void {
    form.home_lat = Number(value[0].toFixed(6));
    form.home_lng = Number(value[1].toFixed(6));
}

const inferError = ref<string | null>(null);
const inferring = ref(false);

async function useMostCommonStart(): Promise<void> {
    inferring.value = true;
    inferError.value = null;
    const { ok, data } = await postJson<{
        lat: number;
        lng: number;
        message?: string;
    }>(HomeLocationController.infer.url());
    inferring.value = false;

    if (ok) {
        setMarker([data.lat, data.lng]);
    } else {
        inferError.value = data.message ?? 'Could not infer a home location.';
    }
}

function save(): void {
    form.patch(update().url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Home & routes" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Home & routes"
            description="Set where your workouts start, so route suggestions begin from home."
        />

        <div
            v-if="status"
            class="rounded-md bg-muted px-4 py-3 text-sm text-muted-foreground"
        >
            {{ status }}
        </div>

        <div
            v-if="!routingConfigured"
            class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-600 dark:text-amber-400"
        >
            Route generation needs an openrouteservice API key
            (<code>ORS_API_KEY</code>). Set your home now; suggestions light up
            once the key is configured.
        </div>

        <p class="text-sm text-muted-foreground">
            Click the map to drop your home pin, or pull it from your most
            frequent activity start.
        </p>

        <RouteMap
            :marker="marker"
            interactive
            height-class="h-80"
            @update:marker="setMarker"
        />

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="home_lat">Latitude</Label>
                <Input
                    id="home_lat"
                    v-model.number="form.home_lat"
                    type="number"
                    step="0.000001"
                    placeholder="52.090000"
                />
            </div>
            <div class="grid gap-2">
                <Label for="home_lng">Longitude</Label>
                <Input
                    id="home_lng"
                    v-model.number="form.home_lng"
                    type="number"
                    step="0.000001"
                    placeholder="5.120000"
                />
            </div>
        </div>

        <div v-if="inferError" class="text-sm text-destructive">
            {{ inferError }}
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <Button
                type="button"
                :disabled="form.processing || !hasHome"
                @click="save"
            >
                Save home
            </Button>
            <Button
                type="button"
                variant="outline"
                :disabled="inferring"
                @click="useMostCommonStart"
            >
                {{ inferring ? 'Finding…' : 'Use my most common start' }}
            </Button>
        </div>
    </div>
</template>
