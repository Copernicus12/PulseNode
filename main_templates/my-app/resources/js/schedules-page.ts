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

function formatNumber(value: unknown, fractionDigits: number): string {
    const numericValue = Number(value);

    if (Number.isNaN(numericValue)) {
        return Number(0).toFixed(fractionDigits);
    }

    return numericValue.toFixed(fractionDigits);
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

if (host && payload?.textContent) {
    try {
        const props = JSON.parse(payload.textContent);
        createApp(SchedulesCreateDialog, props).mount(host);

        void refreshSchedulesPage();
        refreshTimer = window.setInterval(refreshSchedulesPage, 10000);
    } catch (error) {
        console.error('Unable to mount schedules page', error);
    }
}

window.addEventListener('beforeunload', () => {
    if (refreshTimer !== undefined) {
        window.clearInterval(refreshTimer);
    }
});
