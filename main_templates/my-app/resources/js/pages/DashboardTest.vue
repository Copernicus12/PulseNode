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
const relayStatuses = ref<Record<number, string>>({
    1: 'Standby',
    2: 'Standby',
    3: 'Standby',
});
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

const relayChip = computed(() => (data.value.relay ? 'PORNIT' : 'OPRIT'));

const relayCards = [
    { id: 1, title: 'Living Room', meta: 'Priza A · 220V' },
    { id: 2, title: 'Kitchen Circuit', meta: 'Priza B · 220V' },
    { id: 3, title: 'Office Desk', meta: 'Priza C · 220V' },
];

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
        relayCards.forEach((relay) => {
            relayStatuses.value[relay.id] = relayChip.value;
        });
    } catch {
        apiStatus.value = 'Nu pot citi /api/latest.';
    }
};

const relay = async (relayId: number, state: 'on' | 'off') => {
    if (relayBusy.value) {
        return;
    }

    const nextState = state === 'on';
    const previousState = data.value.relay;
    data.value.relay = nextState;
    relayBusy.value = true;
    relayStatus.value = nextState ? 'Pornesc releul...' : 'Opresc releul...';
    relayStatuses.value[relayId] = relayStatus.value;

    try {
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
        relayStatuses.value[relayId] = relayChip.value;
        pushToast('Releu actualizat', relayStatus.value, 'success');
    } catch (error) {
        data.value.relay = previousState;
        relayStatus.value = 'Comanda a esuat.';
        relayStatuses.value[relayId] = relayStatus.value;
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
        <div class="relative flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-3xl border border-slate-200 bg-white p-6 shadow-xl">
            <div class="pointer-events-none absolute inset-0 -z-10">
                <div class="absolute -left-20 top-0 h-56 w-56 rounded-full bg-emerald-200/40 blur-3xl"></div>
                <div class="absolute right-10 top-24 h-64 w-64 rounded-full bg-amber-200/45 blur-3xl"></div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">
                        Minimalist Dashboard
                    </p>
                    <h1 class="mt-2 text-3xl font-semibold text-slate-900">Overview</h1>
                    <p class="mt-2 text-sm text-slate-500">{{ apiStatus }}</p>
                </div>
                <div class="flex items-center gap-4">
                    <span
                        class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-emerald-700"
                    >
                        <span class="size-2 rounded-full bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.7)]"></span>
                        {{ onlineLabel }}
                    </span>
                    <div class="text-xs text-slate-500">{{ updatedAtLabel }}</div>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-[2fr_1fr_1fr]">
                <div class="rounded-3xl bg-slate-950 p-6 text-white shadow-[0_25px_45px_rgba(15,23,42,0.35)]">
                    <p class="text-xs font-semibold uppercase tracking-[0.35em] text-white/50">Active Load</p>
                    <div class="mt-5 text-5xl font-semibold">
                        {{ data.power.toFixed(1) }} <span class="text-base font-medium text-white/60">W</span>
                    </div>
                    <p class="mt-2 text-sm text-white/50">Stability 0.4% · Factor 0.98</p>
                </div>
                <div class="rounded-3xl bg-slate-950 p-6 text-white shadow-[0_25px_45px_rgba(15,23,42,0.35)]">
                    <p class="text-xs font-semibold uppercase tracking-[0.35em] text-white/50">Voltage</p>
                    <div class="mt-5 text-4xl font-semibold">
                        {{ data.voltage.toFixed(1) }} <span class="text-base font-medium text-white/60">V</span>
                    </div>
                    <p class="mt-2 text-sm text-white/50">Target 230V · Grid ok</p>
                </div>
                <div class="rounded-3xl bg-slate-950 p-6 text-white shadow-[0_25px_45px_rgba(15,23,42,0.35)]">
                    <p class="text-xs font-semibold uppercase tracking-[0.35em] text-white/50">Energy</p>
                    <div class="mt-5 text-4xl font-semibold">
                        {{ data.energy.toFixed(2) }} <span class="text-base font-medium text-white/60">kWh</span>
                    </div>
                    <p class="mt-2 text-sm text-white/50">24h usage</p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div
                    v-for="relayItem in relayCards"
                    :key="relayItem.id"
                    class="rounded-3xl bg-slate-950 p-6 text-white shadow-[0_25px_45px_rgba(15,23,42,0.35)]"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.35em] text-white/50">
                                Relay 0{{ relayItem.id }}
                            </p>
                            <p class="mt-3 text-lg font-semibold">{{ relayItem.title }}</p>
                            <p class="mt-1 text-xs text-white/50">{{ relayItem.meta }}</p>
                        </div>
                        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs uppercase tracking-[0.2em] text-white/70">
                            {{ relayStatuses[relayItem.id] }}
                        </span>
                    </div>
                    <div class="mt-5 flex items-center gap-3">
                        <button
                            class="rounded-full bg-emerald-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-emerald-700"
                            :disabled="relayBusy"
                            @click="relay(relayItem.id, 'on')"
                        >
                            On
                        </button>
                        <button
                            class="rounded-full bg-rose-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-rose-700"
                            :disabled="relayBusy"
                            @click="relay(relayItem.id, 'off')"
                        >
                            Off
                        </button>
                        <span class="ml-auto text-xs text-white/50">Curent: {{ data.current.toFixed(2) }} A</span>
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
