import { createApp } from 'vue';
import SchedulesCreateDialog from '@/components/devices/SchedulesCreateDialog.vue';
import { initializeTheme } from '@/composables/useAppearance';

initializeTheme();

const host = document.getElementById('schedules-page-root');
const payload = document.getElementById('schedules-page-props');

type LiveSocketOverview = {
    index: number;
    label: string;
    power: number;
    current: number;
    status: string;
    state_label: string;
    status_label: string;
    schedule_count: number;
    next_schedule?: { name?: string | null } | null;
};

type LiveSchedulesPayload = {
    latest?: Record<string, unknown>;
    scheduleStats?: {
        active_rules?: number;
        scheduled_windows?: number;
        coverage?: string;
        next_trigger?: string;
    };
    socketOverview?: LiveSocketOverview[];
};

type LatestTelemetryPayload = {
    current?: number;
    current_1?: number;
    current_2?: number;
    current_3?: number;
    power?: number;
    power_1?: number;
    power_2?: number;
    power_3?: number;
    relay_1?: boolean;
    relay_2?: boolean;
    relay_3?: boolean;
    updated_at?: string | null;
};

const statusClasses: Record<string, string> = {
    matched: 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/30',
    unknown: 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/30',
    idle: 'bg-muted text-muted-foreground ring-1 ring-border/40',
    off: 'bg-muted text-muted-foreground ring-1 ring-border/40',
    high_load: 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/30',
    overload: 'bg-red-500/15 text-red-300 ring-1 ring-red-500/30',
    offline: 'bg-muted text-muted-foreground ring-1 ring-border/40',
};

let refreshTimer: number | undefined;
let latestTelemetryHandler: ((event: Event) => void) | undefined;
const liveWindow = window as Window & {
    __pulsenodeLatest?: LatestTelemetryPayload;
};

function formatNumber(value: unknown, fractionDigits: number): string {
    const numericValue = Number(value);

    if (Number.isNaN(numericValue)) {
        return Number(0).toFixed(fractionDigits);
    }

    return numericValue.toFixed(fractionDigits);
}

function displayCurrent(value: unknown): number {
    const current = Number(value);

    if (!Number.isFinite(current) || Math.abs(current) < 0.05) {
        return 0;
    }

    return current;
}

function isOnline(updatedAt?: string | null): boolean {
    if (!updatedAt) {
        return false;
    }

    const timestamp = Date.parse(updatedAt);
    if (!Number.isFinite(timestamp)) {
        return false;
    }

    return Date.now() - timestamp <= 5 * 60 * 1000;
}

function deriveSocketStatus(
    telemetry: LatestTelemetryPayload,
    socketIndex: number,
): { status: string; stateLabel: string } {
    if (!isOnline(telemetry.updated_at)) {
        return { status: 'offline', stateLabel: 'Idle' };
    }

    const relayOn = Boolean(telemetry[`relay_${socketIndex}` as keyof LatestTelemetryPayload]);
    if (!relayOn) {
        return { status: 'off', stateLabel: 'Idle' };
    }

    const power = Math.max(
        0,
        Number(telemetry[`power_${socketIndex}` as keyof LatestTelemetryPayload] ?? 0),
    );

    if (power <= 0.1) {
        return { status: 'idle', stateLabel: 'Live' };
    }

    if (power > 2500) {
        return { status: 'overload', stateLabel: 'Live' };
    }

    if (power > 1800) {
        return { status: 'high_load', stateLabel: 'Live' };
    }

    return { status: 'matched', stateLabel: 'Live' };
}

function updateSummary(payload: LiveSchedulesPayload) {
    const activeRules = document.querySelector<HTMLElement>('[data-schedules-active-rules]');
    const nextTrigger = document.querySelector<HTMLElement>('[data-schedules-next-trigger]');
    const coverage = document.querySelector<HTMLElement>('[data-schedules-coverage]');

    if (activeRules && payload.scheduleStats?.active_rules !== undefined) {
        activeRules.textContent = `${payload.scheduleStats.active_rules} active`;
    }

    if (nextTrigger && payload.scheduleStats?.next_trigger) {
        nextTrigger.textContent = `Next: ${payload.scheduleStats.next_trigger}`;
    }

    if (coverage && payload.scheduleStats?.coverage) {
        coverage.textContent = payload.scheduleStats.coverage;
    }
}

function updateSocketCards(payload: LiveSchedulesPayload) {
    for (const socket of payload.socketOverview ?? []) {
        const card = document.querySelector<HTMLElement>(`[data-socket-card="${socket.index}"]`);

        if (!card) {
            continue;
        }

        const label = card.querySelector<HTMLElement>('[data-socket-label]');
        const statusLabel = card.querySelector<HTMLElement>('[data-socket-status-label]');
        const status = card.querySelector<HTMLElement>('[data-socket-status]');
        const power = card.querySelector<HTMLElement>('[data-socket-power]');
        const current = card.querySelector<HTMLElement>('[data-socket-current]');
        const scheduleCount = card.querySelector<HTMLElement>('[data-socket-schedule-count]');
        const nextSchedule = card.querySelector<HTMLElement>('[data-socket-next-schedule]');

        if (label) {
            label.textContent = socket.label;
        }

        if (statusLabel) {
            statusLabel.textContent = socket.status_label;
        }

        if (status) {
            status.className = `inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium ${statusClasses[socket.status] ?? statusClasses.idle}`;
            status.textContent = socket.state_label;
        }

        if (power) {
            power.textContent = `${formatNumber(socket.power, 1)} W`;
        }

        if (current) {
            current.textContent = `${formatNumber(socket.current, 3)} A`;
        }

        if (scheduleCount) {
            scheduleCount.textContent = String(socket.schedule_count);
        }

        if (nextSchedule) {
            nextSchedule.textContent = socket.next_schedule?.name ?? 'No schedules yet';
        }
    }
}

function updateSocketTelemetry(telemetry: LatestTelemetryPayload) {
    for (const socketIndex of [1, 2, 3]) {
        const card = document.querySelector<HTMLElement>(`[data-socket-card="${socketIndex}"]`);

        if (!card) {
            continue;
        }

        const power = card.querySelector<HTMLElement>('[data-socket-power]');
        const current = card.querySelector<HTMLElement>('[data-socket-current]');
        const status = card.querySelector<HTMLElement>('[data-socket-status]');

        const socketPower = Number(telemetry[`power_${socketIndex}` as keyof LatestTelemetryPayload] ?? 0);
        const socketCurrent = displayCurrent(
            telemetry[`current_${socketIndex}` as keyof LatestTelemetryPayload] ?? 0,
        );
        const socketStatus = deriveSocketStatus(telemetry, socketIndex);

        if (power) {
            power.textContent = `${formatNumber(socketPower, 1)} W`;
        }

        if (current) {
            current.textContent = `${formatNumber(socketCurrent, 3)} A`;
        }

        if (status) {
            status.className = `inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium ${statusClasses[socketStatus.status] ?? statusClasses.idle}`;
            status.textContent = socketStatus.stateLabel;
        }
    }
}

async function refreshSchedulesPage() {
    if (!payload?.textContent) {
        return;
    }

    try {
        const response = await fetch(window.location.href, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const data = (await response.json()) as LiveSchedulesPayload;
        updateSummary(data);
        updateSocketCards(data);

        if (data.latest) {
            window.dispatchEvent(new CustomEvent('pulsenode:latest', { detail: data.latest }));
        }
    } catch (error) {
        console.warn('Unable to refresh schedules page', error);
    }
}

function handleTelemetryEvent(event: Event) {
    const customEvent = event as CustomEvent<LatestTelemetryPayload>;
    updateSocketTelemetry(customEvent.detail || {});
}

if (host && payload?.textContent) {
    try {
        const props = JSON.parse(payload.textContent);
        createApp(SchedulesCreateDialog, props).mount(host);

        void refreshSchedulesPage();
        refreshTimer = window.setInterval(refreshSchedulesPage, 30000);
        latestTelemetryHandler = handleTelemetryEvent;
        window.addEventListener('pulsenode:latest', latestTelemetryHandler as EventListener);

        if (liveWindow.__pulsenodeLatest) {
            updateSocketTelemetry(liveWindow.__pulsenodeLatest);
        }
    } catch (error) {
        console.error('Unable to mount schedules page', error);
    }
}

window.addEventListener('beforeunload', () => {
    if (refreshTimer !== undefined) {
        window.clearInterval(refreshTimer);
    }

    if (latestTelemetryHandler) {
        window.removeEventListener('pulsenode:latest', latestTelemetryHandler as EventListener);
    }
});
