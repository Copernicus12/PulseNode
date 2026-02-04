<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type EnergyData = {
    voltage: number;
    current: number;
    power: number;
    energy: number;
    relay: boolean;
    updated_at?: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard Test',
        href: '/dashboard-test',
    },
];

const data = ref<EnergyData>({
    voltage: 0,
    current: 0,
    power: 0,
    energy: 0,
    relay: false,
    updated_at: null,
});

type Toast = {
    id: number;
    title: string;
    message: string;
    variant: 'success' | 'error' | 'info';
};

type MetricConfig = {
    key: 'voltage' | 'current' | 'power' | 'energy';
    label: string;
    unit: string;
    max: number;
    color: string;
};

const metrics: MetricConfig[] = [
    { key: 'voltage', label: 'Tensiune', unit: 'V', max: 260, color: '#f59e0b' },
    { key: 'current', label: 'Curent', unit: 'A', max: 30, color: '#38bdf8' },
    { key: 'power', label: 'Putere', unit: 'W', max: 5000, color: '#22c55e' },
    { key: 'energy', label: 'Energie', unit: 'kWh', max: 50, color: '#e879f9' },
];

const WINDOW_MS = 60_000;
const CHART_WIDTH = 360;
const CHART_HEIGHT = 120;
const GAUGE_RADIUS = 38;
const GAUGE_CIRCUMFERENCE = 2 * Math.PI * GAUGE_RADIUS;

const relayStatus = ref('Sistem gata de comenzi.');
const apiStatus = ref('Live din MQTT: esp32/data | Comenzi: esp32/cmd');
const relayBusy = ref(false);
const toasts = ref<Toast[]>([]);
const chartSeries = ref<Array<{ at: number; value: number }>>([]);
const nowMs = ref(Date.now());

let refreshTimer: ReturnType<typeof setInterval> | null = null;
let clockTimer: ReturnType<typeof setInterval> | null = null;

const online = computed(() => {
    if (!data.value.updated_at) {
        return false;
    }

    const ageMs = nowMs.value - new Date(data.value.updated_at).getTime();
    return ageMs >= 0 && ageMs <= 8_000;
});

const onlineLabel = computed(() => (online.value ? 'Online' : 'Offline'));

const updatedAtLabel = computed(() => {
    if (!data.value.updated_at) {
        return 'Nu exista sincronizare inca.';
    }

    return new Date(data.value.updated_at).toLocaleString();
});

const syncAgeLabel = computed(() => {
    if (!data.value.updated_at) {
        return 'Astept primul pachet de date.';
    }

    const seconds = Math.max(
        0,
        Math.round((nowMs.value - new Date(data.value.updated_at).getTime()) / 1000),
    );

    return seconds === 0 ? 'Chiar acum' : `Acum ${seconds}s`;
});

const relayButtonLabel = computed(() => (data.value.relay ? 'Opreste releul' : 'Porneste releul'));

const chartMax = computed(() => {
    const peak = Math.max(250, ...chartSeries.value.map((entry) => entry.value));
    return peak * 1.1;
});

const chartLinePath = computed(() => {
    if (chartSeries.value.length < 2) {
        return '';
    }

    const points = chartSeries.value.map((entry, index) => {
        const x = (index / (chartSeries.value.length - 1)) * CHART_WIDTH;
        const y =
            CHART_HEIGHT -
            Math.min(1, entry.value / Math.max(1, chartMax.value)) * CHART_HEIGHT;

        return `${x.toFixed(2)},${y.toFixed(2)}`;
    });

    return `M ${points.join(' L ')}`;
});

const chartFillPath = computed(() => {
    if (!chartLinePath.value || chartSeries.value.length < 2) {
        return '';
    }

    return `${chartLinePath.value} L ${CHART_WIDTH},${CHART_HEIGHT} L 0,${CHART_HEIGHT} Z`;
});

const pushToast = (
    title: string,
    message: string,
    variant: Toast['variant'] = 'info',
) => {
    const id = Date.now() + Math.floor(Math.random() * 1000);
    toasts.value.push({ id, title, message, variant });
    setTimeout(() => {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }, 3000);
};

const gaugeOffset = (value: number, max: number) => {
    const percent = Math.max(0, Math.min(1, value / Math.max(1, max)));
    return GAUGE_CIRCUMFERENCE * (1 - percent);
};

const recordChartPoint = (powerValue: number) => {
    const now = Date.now();
    chartSeries.value = [...chartSeries.value, { at: now, value: powerValue }].filter(
        (point) => now - point.at <= WINDOW_MS,
    );
};

const refresh = async () => {
    try {
        const response = await fetch('/api/latest');
        if (!response.ok) {
            throw new Error('API latest indisponibil');
        }

        const payload = (await response.json()) as EnergyData;
        data.value = payload;
        apiStatus.value = 'Flux activ: sincronizare la 1.5s';
        recordChartPoint(payload.power);
    } catch {
        apiStatus.value = 'Nu pot citi /api/latest.';
    }
};

const relay = async () => {
    if (relayBusy.value) {
        return;
    }

    const nextState = !data.value.relay;
    const previousState = data.value.relay;
    data.value.relay = nextState;
    relayBusy.value = true;
    relayStatus.value = nextState ? 'Pornesc releul...' : 'Opresc releul...';

    try {
        const state = nextState ? 'on' : 'off';
        const response = await fetch(`/api/relay/${state}`);
        const payload = (await response.json()) as {
            sent?: string;
            published?: boolean;
            message?: string;
            relay?: boolean;
        };

        if (!response.ok) {
            throw new Error(payload.message || 'API relay indisponibil');
        }

        data.value.relay = payload.relay ?? nextState;
        const mode = payload.published ? 'MQTT' : 'local';
        relayStatus.value = `Comanda trimisa (${mode}): ${payload.sent ?? state.toUpperCase()}`;
        pushToast('Releu actualizat', relayStatus.value, 'success');
    } catch (error) {
        data.value.relay = previousState;
        relayStatus.value = 'Comanda a esuat.';
        pushToast(
            'Eroare releu',
            error instanceof Error ? error.message : 'Endpoint indisponibil.',
            'error',
        );
    } finally {
        relayBusy.value = false;
    }
};

onMounted(() => {
    refresh();
    refreshTimer = setInterval(refresh, 1500);
    clockTimer = setInterval(() => {
        nowMs.value = Date.now();
    }, 1000);
});

onBeforeUnmount(() => {
    if (refreshTimer) {
        clearInterval(refreshTimer);
    }
    if (clockTimer) {
        clearInterval(clockTimer);
    }
});
</script>

<template>
    <Head title="Dashboard Test" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="pointer-events-none absolute inset-0 -z-10 opacity-60">
                <div class="absolute -left-24 top-0 h-64 w-64 rounded-full bg-cyan-500/20 blur-3xl"></div>
                <div class="absolute right-0 top-16 h-72 w-72 rounded-full bg-amber-500/20 blur-3xl"></div>
            </div>

            <div class="rounded-2xl border border-sidebar-border/70 bg-card/80 p-5 backdrop-blur-sm dark:border-sidebar-border">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">ESP32 Control Center</h1>
                        <p class="mt-1 text-sm text-muted-foreground">{{ apiStatus }}</p>
                    </div>
                    <div
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium"
                        :class="online ? 'border-emerald-500/50 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'border-rose-500/50 bg-rose-500/15 text-rose-700 dark:text-rose-300'"
                    >
                        <span class="size-2 rounded-full" :class="online ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                        {{ onlineLabel }}
                    </div>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-3">
                <div class="rounded-2xl border border-sidebar-border/70 bg-card/80 p-5 backdrop-blur-sm dark:border-sidebar-border">
                    <h2 class="text-sm font-medium text-muted-foreground">Stare ESP32</h2>
                    <p class="mt-3 text-xl font-semibold">{{ onlineLabel }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">Ultima sincronizare: {{ updatedAtLabel }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ syncAgeLabel }}</p>
                </div>

                <div class="rounded-2xl border border-sidebar-border/70 bg-card/80 p-5 backdrop-blur-sm dark:border-sidebar-border">
                    <h2 class="text-sm font-medium text-muted-foreground">Relay Quick Toggle</h2>
                    <div class="mt-4 flex items-center gap-4">
                        <button
                            class="relative h-9 w-20 rounded-full transition"
                            :class="data.relay ? 'bg-emerald-500/80' : 'bg-zinc-400/60 dark:bg-zinc-700/70'"
                            :disabled="relayBusy"
                            @click="relay"
                        >
                            <span
                                class="absolute top-1 h-7 w-7 rounded-full bg-white shadow transition-all"
                                :class="data.relay ? 'left-12' : 'left-1'"
                            ></span>
                        </button>
                        <div>
                            <p class="text-sm font-medium">{{ relayButtonLabel }}</p>
                            <p class="text-xs text-muted-foreground">{{ relayStatus }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-sidebar-border/70 bg-card/80 p-5 backdrop-blur-sm dark:border-sidebar-border">
                    <h2 class="text-sm font-medium text-muted-foreground">Putere Live (60s)</h2>
                    <svg
                        class="mt-4 h-32 w-full"
                        viewBox="0 0 360 120"
                        preserveAspectRatio="none"
                    >
                        <defs>
                            <linearGradient id="powerLine" x1="0" y1="0" x2="1" y2="0">
                                <stop offset="0%" stop-color="#22d3ee" />
                                <stop offset="100%" stop-color="#22c55e" />
                            </linearGradient>
                            <linearGradient id="powerFill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#22d3ee" stop-opacity="0.35" />
                                <stop offset="100%" stop-color="#22d3ee" stop-opacity="0.02" />
                            </linearGradient>
                        </defs>
                        <line x1="0" y1="120" x2="360" y2="120" stroke="currentColor" class="text-sidebar-border/70" />
                        <path v-if="chartFillPath" :d="chartFillPath" fill="url(#powerFill)" />
                        <path
                            v-if="chartLinePath"
                            :d="chartLinePath"
                            fill="none"
                            stroke="url(#powerLine)"
                            stroke-width="3"
                            stroke-linecap="round"
                        />
                    </svg>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="metric in metrics"
                    :key="metric.key"
                    class="rounded-2xl border border-sidebar-border/70 bg-card/80 p-4 backdrop-blur-sm dark:border-sidebar-border"
                >
                    <p class="text-sm text-muted-foreground">{{ metric.label }}</p>
                    <div class="mt-3 flex items-center gap-3">
                        <svg class="h-24 w-24 shrink-0 -rotate-90" viewBox="0 0 100 100">
                            <circle
                                cx="50"
                                cy="50"
                                :r="GAUGE_RADIUS"
                                stroke="currentColor"
                                stroke-width="8"
                                fill="none"
                                class="text-sidebar-border/70"
                            />
                            <circle
                                cx="50"
                                cy="50"
                                :r="GAUGE_RADIUS"
                                :stroke="metric.color"
                                stroke-width="8"
                                fill="none"
                                stroke-linecap="round"
                                :stroke-dasharray="GAUGE_CIRCUMFERENCE"
                                :stroke-dashoffset="gaugeOffset(data[metric.key], metric.max)"
                                class="transition-all duration-700 ease-out"
                            />
                        </svg>
                        <div>
                            <p class="text-2xl font-bold">
                                {{ data[metric.key].toFixed(metric.key === 'energy' ? 2 : 1) }}
                            </p>
                            <p class="text-sm text-muted-foreground">{{ metric.unit }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="fixed right-4 top-20 z-50 flex w-80 flex-col gap-2">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="rounded-lg border p-3 shadow-lg backdrop-blur-sm"
                    :class="toast.variant === 'success'
                        ? 'border-emerald-500/60 bg-emerald-500/15'
                        : toast.variant === 'error'
                          ? 'border-rose-500/60 bg-rose-500/15'
                          : 'border-cyan-500/60 bg-cyan-500/15'"
                >
                    <p class="text-sm font-semibold">{{ toast.title }}</p>
                    <p class="text-xs text-muted-foreground">{{ toast.message }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
