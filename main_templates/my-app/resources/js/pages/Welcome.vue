<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ThemeModeSwitcher from '@/components/ThemeModeSwitcher.vue';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const highlights = [
    'Live relays',
    'Realtime telemetry',
    'Energy history',
    'Quick diagnostics',
];

const quickStats = [
    { label: 'Relay Channels', value: '4' },
    { label: 'Latency', value: '<1s' },
    { label: 'Status', value: 'Online' },
];
</script>

<template>
    <Head title="Welcome" />

    <div class="relative min-h-screen overflow-hidden bg-background text-foreground">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -top-24 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-primary/12 blur-3xl"></div>
            <div class="absolute bottom-0 right-0 h-80 w-80 rounded-full bg-muted/70 blur-3xl"></div>
        </div>

        <div class="relative mx-auto flex min-h-screen w-full max-w-6xl flex-col px-6 py-6 lg:px-8 lg:py-8">
            <header class="rounded-3xl border border-border/80 bg-card/75 p-4 backdrop-blur-xl">
                <nav class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-sm font-semibold text-primary-foreground">
                            PN
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.22em] text-muted-foreground">PulseNode</p>
                            <p class="text-sm font-semibold">ESP32 Control Center</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <ThemeModeSwitcher
                            compact
                            button-class="border border-border bg-card text-foreground hover:bg-muted"
                        />

                        <template v-if="$page.props.auth.user">
                            <Link
                                :href="dashboard()"
                                class="inline-flex items-center rounded-xl border border-border bg-card px-4 py-2 text-sm font-medium transition hover:bg-muted"
                            >
                                Open dashboard
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                :href="login()"
                                class="inline-flex items-center rounded-xl border border-border bg-card px-4 py-2 text-sm font-medium transition hover:bg-muted"
                            >
                                Log in
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="inline-flex items-center rounded-xl bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                            >
                                Create account
                            </Link>
                        </template>
                    </div>
                </nav>
            </header>

            <main class="mt-6 grid flex-1 gap-6 lg:grid-cols-[1.3fr_0.7fr]">
                <section class="rounded-3xl border border-border/80 bg-card/75 p-6 backdrop-blur-xl lg:p-8">
                    <p class="text-xs uppercase tracking-[0.32em] text-muted-foreground">Smart Power Dashboard</p>
                    <h1 class="mt-4 max-w-2xl text-3xl font-semibold leading-tight sm:text-4xl lg:text-5xl">
                        Clean control for your ESP32 setup.
                    </h1>
                    <p class="mt-4 max-w-xl text-sm leading-7 text-muted-foreground sm:text-base">
                        Everything important in one place: relays, telemetry, history and diagnostics.
                    </p>

                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <template v-if="$page.props.auth.user">
                            <Link
                                :href="dashboard()"
                                class="inline-flex items-center rounded-2xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                            >
                                Open dashboard
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                :href="login()"
                                class="inline-flex items-center rounded-2xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                            >
                                Log in
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="inline-flex items-center rounded-2xl border border-border bg-card px-5 py-2.5 text-sm font-medium transition hover:bg-muted"
                            >
                                Create account
                            </Link>
                        </template>
                    </div>

                    <div class="mt-8 grid gap-3 sm:grid-cols-3">
                        <div
                            v-for="stat in quickStats"
                            :key="stat.label"
                            class="rounded-2xl border border-border bg-muted/35 p-4"
                        >
                            <p class="text-xs uppercase tracking-[0.2em] text-muted-foreground">{{ stat.label }}</p>
                            <p class="mt-2 text-2xl font-semibold">{{ stat.value }}</p>
                        </div>
                    </div>
                </section>

                <aside class="rounded-3xl border border-border/80 bg-card/75 p-6 backdrop-blur-xl lg:p-8">
                    <p class="text-xs uppercase tracking-[0.25em] text-muted-foreground">What you get</p>
                    <h2 class="mt-3 text-xl font-semibold">Fast, focused workspace</h2>

                    <div class="mt-6 grid grid-cols-2 gap-2">
                        <article
                            v-for="item in highlights"
                            :key="item"
                            class="rounded-xl border border-border bg-muted/35 px-3 py-2 text-sm font-medium"
                        >
                            {{ item }}
                        </article>
                    </div>

                    <div class="mt-6 rounded-2xl border border-border bg-muted/35 p-4">
                        <p class="text-sm text-muted-foreground">Ready in seconds.</p>
                        <p class="mt-1 text-sm font-medium">Sign in and start controlling devices.</p>
                    </div>
                </aside>
            </main>
        </div>
    </div>
</template>
