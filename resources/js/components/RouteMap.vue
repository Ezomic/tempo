<script setup lang="ts">
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        coordinates?: [number, number][];
        marker?: [number, number] | null;
        interactive?: boolean;
        heightClass?: string;
    }>(),
    {
        coordinates: () => [],
        marker: null,
        interactive: false,
        heightClass: 'h-72',
    },
);

const emit = defineEmits<{ 'update:marker': [value: [number, number]] }>();

const el = ref<HTMLDivElement | null>(null);
let map: L.Map | null = null;
let line: L.Polyline | null = null;
let pin: L.CircleMarker | null = null;

const LIME = '#84cc16';

function drawLine(): void {
    if (!map) {
        return;
    }

    line?.remove();
    line = null;

    if (props.coordinates.length > 1) {
        line = L.polyline(props.coordinates, { color: LIME, weight: 5 }).addTo(
            map,
        );
        map.fitBounds(line.getBounds(), { padding: [24, 24] });
    }
}

function drawPin(): void {
    if (!map) {
        return;
    }

    pin?.remove();
    pin = null;

    if (props.marker) {
        pin = L.circleMarker(props.marker, {
            radius: 8,
            color: LIME,
            fillColor: LIME,
            fillOpacity: 1,
        }).addTo(map);

        if (props.coordinates.length <= 1) {
            map.setView(props.marker, 14);
        }
    }
}

onMounted(() => {
    if (!el.value) {
        return;
    }

    const center = props.marker ?? props.coordinates[0] ?? [52.09, 5.12];
    map = L.map(el.value).setView(center, 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    if (props.interactive) {
        map.on('click', (event: L.LeafletMouseEvent) => {
            emit('update:marker', [event.latlng.lat, event.latlng.lng]);
        });
    }

    drawLine();
    drawPin();
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
});

watch(() => props.coordinates, drawLine, { deep: true });
watch(() => props.marker, drawPin);
</script>

<template>
    <div
        ref="el"
        :class="heightClass"
        class="w-full overflow-hidden rounded-lg border"
    ></div>
</template>
