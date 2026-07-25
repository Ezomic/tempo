<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, login } from '@/routes';

interface Feature {
    title: string;
    body: string;
    icon: 'load' | 'readiness' | 'plan' | 'self-hosted';
}

const features: Feature[] = [
    {
        icon: 'load',
        title: 'Unified training load',
        body: 'Runs and rides on one scale. HR-based TRIMP makes every session comparable, and an acute-to-chronic ratio shows when you are ramping up too fast.',
    },
    {
        icon: 'readiness',
        title: 'Daily readiness',
        body: 'A morning score from HRV, body battery, and resting heart rate tells you whether today is for intervals or for easy miles.',
    },
    {
        icon: 'plan',
        title: 'A plan that explains itself',
        body: 'Build your week from structured steps with plain-language intensity, then push each workout to your calendar as a real event.',
    },
    {
        icon: 'self-hosted',
        title: 'Yours, self-hosted',
        body: 'Your training data lives on your own server. Standalone passwordless login, no third-party lock-in, works even when everything else is down.',
    },
];

const steps: { n: string; title: string; body: string }[] = [
    {
        n: '01',
        title: 'Connect Garmin',
        body: 'Link your account once. Tempo pulls your activities and daily wellness through your own sync service.',
    },
    {
        n: '02',
        title: 'See your load and readiness',
        body: 'Every run and ride becomes comparable load, alongside a daily readiness score built from your recovery signals.',
    },
    {
        n: '03',
        title: 'Plan your week',
        body: 'Lay out structured workouts and push them straight to your calendar, so your plan lives where you already look.',
    },
];
</script>

<template>
    <Head title="Tempo — your Garmin data, finally useful" />

    <div class="min-h-screen bg-background text-foreground">
        <!-- Header -->
        <header
            class="sticky top-0 z-10 border-b border-border/60 bg-background/80 backdrop-blur"
        >
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6"
            >
                <div class="flex items-center gap-2">
                    <div
                        class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class-name="size-4" />
                    </div>
                    <span
                        class="text-lg font-extrabold tracking-tight lowercase"
                    >
                        tempo
                    </span>
                </div>
                <nav class="flex items-center gap-2">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="inline-flex h-9 items-center rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                    >
                        Go to dashboard
                    </Link>
                    <Link
                        v-else
                        :href="login()"
                        class="inline-flex h-9 items-center rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                    >
                        Log in
                    </Link>
                </nav>
            </div>
        </header>

        <!-- Hero -->
        <section class="mx-auto max-w-6xl px-6 pt-16 pb-12 lg:pt-24">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div>
                    <span
                        class="inline-flex items-center gap-2 rounded-full border border-border px-3 py-1 text-xs font-medium text-muted-foreground"
                    >
                        <span class="size-1.5 rounded-full bg-primary"></span>
                        Self-hosted Garmin training
                    </span>
                    <h1
                        class="mt-5 text-4xl font-extrabold tracking-tight sm:text-5xl"
                    >
                        Train by the signals that matter.
                    </h1>
                    <p class="mt-5 max-w-xl text-lg text-muted-foreground">
                        Tempo pulls your Garmin runs and rides, turns them into
                        one comparable training load, tells you when you are
                        ready to go hard, and pushes your planned week straight
                        to your calendar.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <Link
                            :href="
                                $page.props.auth.user ? dashboard() : login()
                            "
                            class="inline-flex h-11 items-center gap-2 rounded-lg bg-primary px-6 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                        >
                            {{
                                $page.props.auth.user
                                    ? 'Go to dashboard'
                                    : 'Get started'
                            }}
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                class="size-4"
                                aria-hidden="true"
                            >
                                <path
                                    d="M5 12h14M13 6l6 6-6 6"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </Link>
                        <a
                            href="#features"
                            class="inline-flex h-11 items-center rounded-lg border border-border px-6 text-sm font-semibold transition hover:bg-accent"
                        >
                            See how it works
                        </a>
                    </div>
                </div>

                <!-- Hero visual: a stylised readiness + load card -->
                <div class="relative">
                    <div
                        class="rounded-2xl border border-border bg-card p-6 shadow-sm"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold">Today</span>
                            <span class="text-xs text-muted-foreground"
                                >Readiness</span
                            >
                        </div>
                        <div class="mt-4 flex items-center gap-6">
                            <div class="relative size-28 shrink-0">
                                <svg
                                    viewBox="0 0 120 120"
                                    class="size-28 -rotate-90"
                                >
                                    <circle
                                        cx="60"
                                        cy="60"
                                        r="52"
                                        fill="none"
                                        class="stroke-muted"
                                        stroke-width="10"
                                    />
                                    <circle
                                        cx="60"
                                        cy="60"
                                        r="52"
                                        fill="none"
                                        class="stroke-primary"
                                        stroke-width="10"
                                        stroke-linecap="round"
                                        stroke-dasharray="326.7"
                                        stroke-dashoffset="72"
                                    />
                                </svg>
                                <div
                                    class="absolute inset-0 flex flex-col items-center justify-center"
                                >
                                    <span class="text-2xl font-extrabold"
                                        >78</span
                                    >
                                    <span
                                        class="text-xs font-semibold text-primary"
                                        >Ready</span
                                    >
                                </div>
                            </div>
                            <div class="flex-1 space-y-2">
                                <div
                                    class="flex items-center justify-between text-sm"
                                >
                                    <span class="text-muted-foreground"
                                        >HRV</span
                                    >
                                    <span class="font-medium">Balanced</span>
                                </div>
                                <div
                                    class="flex items-center justify-between text-sm"
                                >
                                    <span class="text-muted-foreground"
                                        >Body battery</span
                                    >
                                    <span class="font-medium">82</span>
                                </div>
                                <div
                                    class="flex items-center justify-between text-sm"
                                >
                                    <span class="text-muted-foreground"
                                        >Resting HR</span
                                    >
                                    <span class="font-medium">48 bpm</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6">
                            <div
                                class="mb-2 flex items-center justify-between text-xs text-muted-foreground"
                            >
                                <span>Weekly load</span>
                                <span>run + bike</span>
                            </div>
                            <div class="flex items-end gap-1.5">
                                <div
                                    v-for="(h, i) in [
                                        40, 55, 35, 70, 50, 85, 60, 95,
                                    ]"
                                    :key="i"
                                    class="flex-1 rounded-sm"
                                    :class="
                                        i === 7 ? 'bg-primary' : 'bg-primary/30'
                                    "
                                    :style="{ height: `${h}px` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="mx-auto max-w-6xl px-6 py-16">
            <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
                Everything a solo athlete actually needs
            </h2>
            <p class="mt-2 max-w-2xl text-muted-foreground">
                The three things Garmin Connect does poorly for one person
                training seriously, done properly.
            </p>
            <div class="mt-10 grid gap-5 sm:grid-cols-2">
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="rounded-2xl border border-border bg-card p-6"
                >
                    <div
                        class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            class="size-5"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <polyline
                                v-if="feature.icon === 'load'"
                                points="3 17 9 11 13 15 21 7"
                            />
                            <path
                                v-else-if="feature.icon === 'readiness'"
                                d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"
                            />
                            <g v-else-if="feature.icon === 'plan'">
                                <rect
                                    x="3"
                                    y="4"
                                    width="18"
                                    height="18"
                                    rx="2"
                                />
                                <path d="M16 2v4M8 2v4M3 10h18" />
                            </g>
                            <path
                                v-else
                                d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3Z"
                            />
                        </svg>
                    </div>
                    <h3 class="mt-4 font-semibold">{{ feature.title }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ feature.body }}
                    </p>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section class="border-y border-border bg-card/40">
            <div class="mx-auto max-w-6xl px-6 py-16">
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
                    From watch to plan in three steps
                </h2>
                <div class="mt-10 grid gap-8 sm:grid-cols-3">
                    <div v-for="step in steps" :key="step.n">
                        <div class="text-sm font-bold text-primary">
                            {{ step.n }}
                        </div>
                        <h3 class="mt-2 font-semibold">{{ step.title }}</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ step.body }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Closing CTA -->
        <section class="mx-auto max-w-6xl px-6 py-20">
            <div
                class="rounded-2xl bg-primary px-8 py-12 text-center text-primary-foreground"
            >
                <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">
                    Ready to train with intent?
                </h2>
                <p class="mx-auto mt-3 max-w-xl opacity-90">
                    Connect your Garmin and see your load, readiness, and week
                    in one place.
                </p>
                <Link
                    :href="$page.props.auth.user ? dashboard() : login()"
                    class="mt-6 inline-flex h-11 items-center gap-2 rounded-lg bg-background px-6 text-sm font-semibold text-foreground transition hover:opacity-90"
                >
                    {{
                        $page.props.auth.user
                            ? 'Go to dashboard'
                            : 'Get started'
                    }}
                </Link>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-border">
            <div
                class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-6 py-8 text-sm text-muted-foreground sm:flex-row"
            >
                <div class="flex items-center gap-2">
                    <AppLogoIcon class-name="size-4 text-foreground" />
                    <span class="font-semibold text-foreground lowercase"
                        >tempo</span
                    >
                </div>
                <span
                    >Self-hosted endurance training. Your data, your
                    server.</span
                >
            </div>
        </footer>
    </div>
</template>
