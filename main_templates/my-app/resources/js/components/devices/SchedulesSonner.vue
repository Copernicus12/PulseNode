<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import { Toaster } from '@/components/ui/sonner';

type AppToastNotification = {
    level?: 'success' | 'info' | 'warning' | 'error';
    title?: string;
    message?: string;
    detail?: string | null;
};

const toasterOptions = {
    duration: 4200,
    closeButton: false,
    class: 'w-[22rem] rounded-2xl border border-border/50 bg-[rgba(18,18,18,0.96)] shadow-2xl backdrop-blur-md',
    descriptionClass: 'text-[11px] leading-4 text-muted-foreground',
    classes: {
        title: 'text-xs font-semibold',
        description: 'text-[11px] leading-4 text-muted-foreground',
        actionButton:
            '!h-7 !rounded-lg !border !px-2.5 !text-[11px] !font-medium',
        success:
            'border-emerald-500/45 bg-[rgba(9,34,24,0.97)] text-emerald-50',
        error: 'border-red-500/45 bg-[rgba(42,10,10,0.97)] text-red-50',
        warning:
            'border-amber-500/45 bg-[rgba(34,24,10,0.97)] text-amber-50',
        info: 'border-sky-500/45 bg-[rgba(12,20,30,0.97)] text-sky-50',
    },
};

function showAppNotification(notification: AppToastNotification): void {
    const level = notification.level || 'info';
    const title = notification.title || notification.message || 'Notification';
    const description =
        notification.title && notification.message
            ? notification.message
            : notification.detail ?? undefined;

    const toastFn =
        level === 'success'
            ? toast.success
            : level === 'error'
                ? toast.error
                : level === 'warning'
                    ? toast.warning
                    : toast.info;

    toastFn(title, {
        description,
    });
}

function handleAppToastEvent(event: Event): void {
    const customEvent = event as CustomEvent<AppToastNotification>;
    showAppNotification(customEvent.detail || {});
}

onMounted(() => {
    window.addEventListener(
        'pulsenode:app-toast',
        handleAppToastEvent as EventListener,
    );
});

onBeforeUnmount(() => {
    window.removeEventListener(
        'pulsenode:app-toast',
        handleAppToastEvent as EventListener,
    );
});
</script>

<template>
    <Toaster
        position="top-right"
        :expand="false"
        :visible-toasts="1"
        :toast-options="toasterOptions"
        container-aria-label="Schedule notifications"
    />
</template>
