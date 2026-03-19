<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'

type RelayCommandGuard = {
    can_turn_on: boolean
    reason: string | null
    message: string | null
    age_seconds: number | null
    max_age_seconds: number
    last_seen_at: string | null
}

const props = defineProps<{
    initialGuard: Partial<RelayCommandGuard>
}>()

type RelayCommandNotification = {
    key: number
    message: string
    detail: string | null
}

function normalizeGuard(raw: Partial<RelayCommandGuard> | null | undefined): RelayCommandGuard {
    const maxAgeSeconds = Number(raw?.max_age_seconds ?? 90)
    const ageSeconds = Number(raw?.age_seconds)

    return {
        can_turn_on: Boolean(raw?.can_turn_on),
        reason: typeof raw?.reason === 'string' ? raw.reason : null,
        message: typeof raw?.message === 'string' ? raw.message : null,
        age_seconds: Number.isFinite(ageSeconds) ? ageSeconds : null,
        max_age_seconds: Number.isFinite(maxAgeSeconds) ? maxAgeSeconds : 90,
        last_seen_at: typeof raw?.last_seen_at === 'string' ? raw.last_seen_at : null,
    }
}

const guard = ref<RelayCommandGuard>(normalizeGuard(props.initialGuard))
const notification = ref<RelayCommandNotification | null>(null)
const isVisible = ref(false)
const teleportTarget = ref('body')
const isDashboardInline = ref(false)
const positionStyle = ref<Record<string, string>>({
    left: '50%',
    top: '1rem',
    width: 'min(48rem, calc(100vw - 2rem))',
    transform: 'translateX(-50%)',
})
let hideTimer: number | null = null
let scrollContainer: HTMLElement | null = null
let scrollDismissHandler: (() => void) | null = null

const detailText = computed(() => {
    if (guard.value.last_seen_at === null) {
        return 'No telemetry has been received yet from the ESP32.'
    }

    if (guard.value.age_seconds === null) {
        return null
    }

    if (guard.value.age_seconds < 60) {
        return `Last telemetry arrived ${guard.value.age_seconds}s ago.`
    }

    const minutes = Math.floor(guard.value.age_seconds / 60)
    return `Last telemetry arrived ${minutes} min ago. Power-on is blocked after ${guard.value.max_age_seconds}s without fresh data.`
})

const shouldShow = computed(() => !guard.value.can_turn_on && Boolean(guard.value.message))
const compactMessage = computed(() => {
    if (guard.value.reason === 'never_seen') {
        return 'ESP32 has not sent telemetry yet.'
    }

    if (guard.value.reason === 'stale') {
        return 'ESP32 telemetry is stale. Power-on is blocked.'
    }

    return notification.value?.message || 'Power-on is unavailable right now.'
})

function handleGuardEvent(event: Event): void {
    const customEvent = event as CustomEvent<Partial<RelayCommandGuard>>
    guard.value = normalizeGuard(customEvent.detail)
}

function clearHideTimer(): void {
    if (hideTimer !== null) {
        window.clearTimeout(hideTimer)
        hideTimer = null
    }
}

function dismissNotification(): void {
    clearHideTimer()
    if (!notification.value || !isVisible.value) {
        return
    }

    isVisible.value = false
}

function clearNotification(): void {
    notification.value = null
}

function updatePosition(): void {
    if (isDashboardInline.value) {
        return
    }

    const searchInput = document.getElementById('global-search-input')

    if (searchInput instanceof HTMLElement) {
        const searchRect = searchInput.getBoundingClientRect()
        positionStyle.value = {
            left: `${Math.round(searchRect.left)}px`,
            top: `${Math.round(searchRect.bottom + 12)}px`,
            width: `${Math.round(searchRect.width)}px`,
            transform: 'translateX(0)',
        }
        return
    }

    positionStyle.value = {
        left: '50%',
        top: '1rem',
        width: 'min(48rem, calc(100vw - 2rem))',
        transform: 'translateX(-50%)',
    }
}

function notificationDetail(nextGuard: RelayCommandGuard): string | null {
    if (nextGuard.last_seen_at === null) {
        return 'No telemetry has been received from the ESP32 yet.'
    }

    if (nextGuard.age_seconds === null) {
        return null
    }

    if (nextGuard.age_seconds < 60) {
        return `Last telemetry arrived ${nextGuard.age_seconds}s ago.`
    }

    const minutes = Math.floor(nextGuard.age_seconds / 60)
    return `Last telemetry arrived ${minutes} min ago. Power-on is blocked after ${nextGuard.max_age_seconds}s without fresh data.`
}

function showNotification(message: string, nextGuard?: Partial<RelayCommandGuard> | null): void {
    const resolvedGuard = nextGuard ? normalizeGuard(nextGuard) : guard.value
    updatePosition()

    notification.value = {
        key: Date.now(),
        message,
        detail: notificationDetail(resolvedGuard),
    }
    isVisible.value = true

    clearHideTimer()
    hideTimer = window.setTimeout(() => {
        isVisible.value = false
        hideTimer = null
    }, 5000)
}

function handleNotificationEvent(event: Event): void {
    const customEvent = event as CustomEvent<{ message?: string; guard?: Partial<RelayCommandGuard> }>
    const nextGuard = customEvent.detail?.guard ? normalizeGuard(customEvent.detail.guard) : guard.value
    guard.value = nextGuard
    showNotification(customEvent.detail?.message || nextGuard.message || 'Comanda nu poate fi trimisa acum.', nextGuard)
}

onMounted(() => {
    if (document.getElementById('dashboard-relay-command-notification-anchor')) {
        teleportTarget.value = '#dashboard-relay-command-notification-anchor'
        isDashboardInline.value = true
    }

    updatePosition()
    scrollContainer = document.getElementById('main-content-scroll')
    window.addEventListener('pulsenode:relay-guard', handleGuardEvent as EventListener)
    window.addEventListener('pulsenode:relay-guard-notification', handleNotificationEvent as EventListener)
    window.addEventListener('resize', updatePosition)
    if (scrollContainer) {
        scrollDismissHandler = () => {
            window.requestAnimationFrame(() => {
                dismissNotification()
            })
        }

        scrollContainer.addEventListener('scroll', scrollDismissHandler, { passive: true })
    }
})

onBeforeUnmount(() => {
    dismissNotification()
    clearNotification()
    window.removeEventListener('pulsenode:relay-guard', handleGuardEvent as EventListener)
    window.removeEventListener('pulsenode:relay-guard-notification', handleNotificationEvent as EventListener)
    window.removeEventListener('resize', updatePosition)
    if (scrollContainer && scrollDismissHandler) {
        scrollContainer.removeEventListener('scroll', scrollDismissHandler)
    }
})
</script>

<template>
    <Teleport :to="teleportTarget">
        <div
            v-if="notification && shouldShow"
            :class="isDashboardInline ? 'pointer-events-none flex items-center' : 'pointer-events-none fixed z-[120] flex justify-center'"
            :style="isDashboardInline ? undefined : positionStyle"
        >
            <Transition
                appear
                enter-active-class="duration-250 ease-out"
                enter-from-class="translate-y-3 scale-95 opacity-0"
                enter-to-class="translate-y-0 scale-100 opacity-100"
                leave-active-class="duration-300 ease-out"
                leave-from-class="translate-y-0 scale-100 opacity-100"
                leave-to-class="-translate-y-2 scale-95 opacity-0"
                @after-leave="clearNotification"
            >
                <Alert
                    v-if="notification && isVisible"
                    variant="destructive"
                    :class="isDashboardInline
                        ? 'pointer-events-auto w-[18rem] rounded-xl border-destructive/30 bg-card/96 px-3 py-2 text-destructive shadow-xl backdrop-blur'
                        : 'pointer-events-auto w-full max-w-none rounded-2xl border-destructive/35 bg-card/96 px-4 py-2.5 text-destructive shadow-2xl backdrop-blur'"
                >
                    <AlertTriangle class="size-4" />
                    <AlertTitle :class="isDashboardInline ? 'text-xs font-semibold' : undefined">Unable to turn relay on</AlertTitle>
                    <AlertDescription :class="isDashboardInline ? 'space-y-1' : 'space-y-1.5'">
                        <p :class="isDashboardInline ? 'text-xs leading-4' : 'text-sm leading-5'">
                            {{ isDashboardInline ? compactMessage : notification.message }}
                        </p>
                        <p v-if="notification.detail && !isDashboardInline" class="text-[11px] leading-4 opacity-90">
                            {{ notification.detail }}
                        </p>
                        <AlertAction
                            :class="isDashboardInline ? 'mt-0 h-6 px-2 text-[10px]' : 'mt-0.5 h-7 px-2.5 text-[11px]'"
                            @click="dismissNotification"
                        >
                            Dismiss
                        </AlertAction>
                    </AlertDescription>
                </Alert>
            </Transition>
        </div>
    </Teleport>
</template>
