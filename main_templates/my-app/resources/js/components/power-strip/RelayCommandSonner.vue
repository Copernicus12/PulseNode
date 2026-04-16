<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

import { Toaster } from '@/components/ui/sonner';

type RelayCommandGuard = {
    can_turn_on: boolean;
    reason: string | null;
    message: string | null;
    age_seconds: number | null;
    max_age_seconds: number;
    last_seen_at: string | null;
};

const props = defineProps<{
    initialGuard: Partial<RelayCommandGuard>;
}>();

type RelayCommandNotification = {
    message: string;
    detail: string | null;
};

const toastId = 'relay-command-guard-toast';
const guard = ref<RelayCommandGuard>(normalizeGuard(props.initialGuard));
const offset = ref<{ top: number; right: number }>({
    top: 16,
    right: 14,
});

const toasterOptions = computed(() => ({
    duration: 5000,
    closeButton: false,
    class: 'w-[20rem] rounded-2xl border border-red-500/55 bg-[rgba(44,10,10,0.98)] shadow-2xl shadow-red-950/30 backdrop-blur-md',
    descriptionClass: 'text-[11px] leading-4 text-red-100/78',
    classes: {
        title: 'text-xs font-semibold text-red-50',
        description: 'text-[11px] leading-4 text-red-100/78',
        actionButton:
            '!h-7 !rounded-lg !border !border-red-400/45 !bg-red-500/18 !px-2.5 !text-[11px] !font-medium !text-red-50 hover:!bg-red-500/28',
        warning: 'border-red-500/55 bg-[rgba(44,10,10,0.98)] text-red-50',
    },
}));

function normalizeGuard(
    raw: Partial<RelayCommandGuard> | null | undefined,
): RelayCommandGuard {
    const maxAgeSeconds = Number(raw?.max_age_seconds ?? 90);
    const ageSeconds = Number(raw?.age_seconds);

    return {
        can_turn_on: Boolean(raw?.can_turn_on),
        reason: typeof raw?.reason === 'string' ? raw.reason : null,
        message: typeof raw?.message === 'string' ? raw.message : null,
        age_seconds: Number.isFinite(ageSeconds) ? ageSeconds : null,
        max_age_seconds: Number.isFinite(maxAgeSeconds) ? maxAgeSeconds : 90,
        last_seen_at:
            typeof raw?.last_seen_at === 'string' ? raw.last_seen_at : null,
    };
}

function notificationDetail(nextGuard: RelayCommandGuard): string | null {
    if (nextGuard.last_seen_at === null) {
        return 'No telemetry has been received from the ESP32 yet.';
    }

    if (nextGuard.age_seconds === null) {
        return null;
    }

    if (nextGuard.age_seconds < 60) {
        return `Last telemetry arrived ${nextGuard.age_seconds}s ago.`;
    }

    const minutes = Math.floor(nextGuard.age_seconds / 60);
    return `Last telemetry arrived ${minutes} min ago. Power-on is blocked after ${nextGuard.max_age_seconds}s without fresh data.`;
}

function updateOffset(): void {
    const anchor = document.getElementById('relay-command-toast-anchor');

    if (!(anchor instanceof HTMLElement)) {
        offset.value = {
            top: 16,
            right: 14,
        };
        return;
    }

    const rect = anchor.getBoundingClientRect();

    offset.value = {
        top: Math.max(16, Math.round(rect.bottom + 10)),
        right: Math.max(14, Math.round(window.innerWidth - rect.right - 86)),
    };
}

function showNotification(notification: RelayCommandNotification): void {
    updateOffset();
    toast.warning(notification.message, {
        id: toastId,
        description: notification.detail ?? undefined,
        action: {
            label: 'Dismiss',
            onClick: () => {
                toast.dismiss(toastId);
            },
        },
    });
}

function handleGuardEvent(event: Event): void {
    const customEvent = event as CustomEvent<Partial<RelayCommandGuard>>;
    guard.value = normalizeGuard(customEvent.detail);
    window.requestAnimationFrame(updateOffset);
}

function handleNotificationEvent(event: Event): void {
    const customEvent = event as CustomEvent<{
        message?: string;
        guard?: Partial<RelayCommandGuard>;
    }>;
    const nextGuard = customEvent.detail?.guard
        ? normalizeGuard(customEvent.detail.guard)
        : guard.value;
    guard.value = nextGuard;

    showNotification({
        message:
            customEvent.detail?.message ||
            nextGuard.message ||
            'Unable to turn relay on right now.',
        detail: notificationDetail(nextGuard),
    });
}

onMounted(() => {
    updateOffset();
    window.addEventListener(
        'pulsenode:relay-guard',
        handleGuardEvent as EventListener,
    );
    window.addEventListener(
        'pulsenode:relay-guard-notification',
        handleNotificationEvent as EventListener,
    );
    window.addEventListener('pulsenode:latest', updateOffset as EventListener);
    window.addEventListener('resize', updateOffset);
});

onBeforeUnmount(() => {
    toast.dismiss(toastId);
    window.removeEventListener(
        'pulsenode:relay-guard',
        handleGuardEvent as EventListener,
    );
    window.removeEventListener(
        'pulsenode:relay-guard-notification',
        handleNotificationEvent as EventListener,
    );
    window.removeEventListener(
        'pulsenode:latest',
        updateOffset as EventListener,
    );
    window.removeEventListener('resize', updateOffset);
});
</script>

<template>
    <Toaster
        position="top-right"
        :expand="false"
        :visible-toasts="1"
        :offset="offset"
        :mobile-offset="{ top: 16, right: 12, left: 16 }"
        :toast-options="toasterOptions"
        container-aria-label="Relay command notifications"
    />
</template>
