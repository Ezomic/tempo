<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <rect x="13" y="6" width="7" height="12" rx="2" fill="currentColor" />
        <rect
            x="6.5"
            y="8"
            width="4"
            height="8"
            rx="1.5"
            fill="currentColor"
            opacity="0.55"
        />
        <rect
            x="2"
            y="10"
            width="2.5"
            height="4"
            rx="1"
            fill="currentColor"
            opacity="0.3"
        />
    </svg>
</template>
