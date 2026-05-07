<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Bell, Search, Users } from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import ThemeModeSwitcher from '@/components/ThemeModeSwitcher.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import { toUrl } from '@/lib/utils';
import { index as accountsIndex } from '@/routes/accounts';
import { latest as apiLatest } from '@/routes/api';
import { latest as notificationsLatest } from '@/routes/api/notifications';
import { edit as editAppearance } from '@/routes/appearance';
import devices from '@/routes/devices';
import { edit as editElectricityBilling } from '@/routes/electricity-billing';
import { index as historyIndex } from '@/routes/history';
import { dashboard } from '@/routes/index';
import { index as notificationsIndex } from '@/routes/notifications';
import powerStrip from '@/routes/power-strip';
import { edit as editProfile } from '@/routes/profile';
import { show as twoFactorShow } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import type { User } from '@/types';

type LatestPayload = {
    current?: number;
    power?: number;
    updated_at?: string | null;
};

type NotificationItem = {
    id?: number;
    title?: string;
    message?: string;
    level?: string;
    created_at?: string | null;
    action_url?: string | null;
};

type AccountsSummary = {
    total: number;
    blocked: number;
    pending_requests: number;
    active_guests: number;
} | null;

type SearchResult = {
    label: string;
    keywords: string;
    kind: 'href' | 'find' | 'panel';
    href?: string;
    panel?: 'notifications' | 'accounts';
    onSelect?: () => void;
};

const page = usePage();
const user = computed(() => page.props.auth.user as User & { role?: string });
const accountsSummary = computed(
    () => (page.props.accountsSummary ?? null) as AccountsSummary,
);

const telemetry = ref({
    power: '0.0W',
    current: '0.000A',
    online: false,
});

const searchQuery = ref('');
const searchOpen = ref(false);
const activeIndex = ref(0);
const searchInput = ref<HTMLInputElement | null>(null);
const searchRoot = ref<HTMLElement | null>(null);
const accountsRoot = ref<HTMLElement | null>(null);
const notificationsRoot = ref<HTMLElement | null>(null);
const accountsOpen = ref(false);
const notificationsOpen = ref(false);
const notifications = ref<NotificationItem[]>([]);
const searchRecentKey = 'pulsenode.search.recent';
const notificationsSeenKey = 'pulsenode.notifications.last_seen_id';

let telemetryTimer: number | undefined;
let notificationsTimer: number | undefined;
let telemetryInFlight = false;
let notificationsInFlight = false;

const CURRENT_DISPLAY_THRESHOLD_A = 0.05;

function displayCurrent(value: unknown): number {
    const current = Number(value ?? 0);
    return Number.isFinite(current) && Math.abs(current) >= CURRENT_DISPLAY_THRESHOLD_A
        ? current
        : 0;
}

const baseSearchResults = computed<SearchResult[]>(() => {
    const items: SearchResult[] = [
        {
            label: 'Go Dashboard',
            keywords: 'go dashboard home',
            kind: 'href',
            href: toUrl(dashboard()),
        },
        {
            label: 'Go Power Strip',
            keywords: 'go power strip sockets',
            kind: 'href',
            href: toUrl(powerStrip.index()),
        },
        {
            label: 'Go History',
            keywords: 'go history logs',
            kind: 'href',
            href: toUrl(historyIndex()),
        },
        {
            label: 'Go Notifications',
            keywords: 'go notifications inbox alerts',
            kind: 'href',
            href: toUrl(notificationsIndex()),
        },
        ...(user.value?.role === 'admin'
            ? [
                  {
                      label: 'Go Settings',
                      keywords: 'go settings appearance electricity billing',
                      kind: 'href' as const,
                      href: '/settings',
                  },
              ]
            : [
                  {
                      label: 'Go Settings',
                      keywords:
                          'go settings profile password appearance two factor',
                      kind: 'href' as const,
                      href: '/settings',
                  },
                  {
                      label: 'Settings: Profile',
                      keywords: 'settings profile account',
                      kind: 'href' as const,
                      href: toUrl(editProfile()),
                  },
                  {
                      label: 'Settings: Password',
                      keywords: 'settings password security',
                      kind: 'href' as const,
                      href: toUrl(editPassword()),
                  },
                  {
                      label: 'Settings: Two-Factor Auth',
                      keywords: 'settings two factor auth 2fa security',
                      kind: 'href' as const,
                      href: toUrl(twoFactorShow()),
                  },
              ]),
        {
            label: 'Settings: Appearance',
            keywords: 'settings appearance theme',
            kind: 'href',
            href: toUrl(editAppearance()),
        },
        {
            label: 'Settings: Electricity Bill',
            keywords:
                'settings electricity bill billing invoice current energy price per wh cost',
            kind: 'href',
            href: toUrl(editElectricityBilling()),
        },
        {
            label: 'Settings: Hardware & Diagnostics',
            keywords:
                'settings hardware diagnostics power strip payload mqtt esp32 relay pinout raw json',
            kind: 'href',
            href: '/settings/power-strip',
        },
        {
            label: 'Go Invoice Archive',
            keywords:
                'go invoice archive invoices bills receipts folders files upload previous months',
            kind: 'href',
            href: '/settings/electricity-billing/archive',
        },
        {
            label: 'Go My Devices Overview',
            keywords: 'go my devices overview',
            kind: 'href',
            href: toUrl(devices.index()),
        },
        {
            label: 'Go Device Profiles',
            keywords: 'go device profiles',
            kind: 'href',
            href: toUrl(devices.profiles.index()),
        },
        {
            label: 'Go Device Plans',
            keywords: 'go device plans',
            kind: 'href',
            href: toUrl(devices.plans.index()),
        },
        {
            label: 'Open Notifications Panel',
            keywords: 'open notifications panel alerts',
            kind: 'panel',
            panel: 'notifications',
        },
    ];

    if (user.value?.role === 'admin') {
        items.push(
            {
                label: 'Go Accounts',
                keywords: 'go accounts users permissions admin',
                kind: 'href',
                href: toUrl(accountsIndex()),
            },
            {
                label: 'Open Accounts Panel',
                keywords: 'open accounts panel admin',
                kind: 'panel',
                panel: 'accounts',
            },
        );
    }

    return items;
});

const searchResults = computed<SearchResult[]>(() => {
    const query = normalize(searchQuery.value);
    const currentTargets = collectCurrentTargets();

    if (!query) {
        return recentItems().concat(baseSearchResults.value).slice(0, 10);
    }

    return baseSearchResults.value
        .concat(currentTargets)
        .filter((item) =>
            normalize(`${item.label} ${item.keywords}`).includes(query),
        )
        .slice(0, 14);
});

const newestNotificationId = computed(() =>
    notifications.value.length ? Number(notifications.value[0]?.id ?? 0) : 0,
);
const unseenNotifications = computed(
    () =>
        notifications.value.filter(
            (item) => Number(item.id ?? 0) > lastSeenNotificationId(),
        ).length,
);

watch(searchResults, (results) => {
    if (results.length === 0) {
        activeIndex.value = 0;
        return;
    }

    if (activeIndex.value >= results.length) {
        activeIndex.value = results.length - 1;
    }
});

watch(notificationsOpen, (isOpen) => {
    if (isOpen && newestNotificationId.value > 0) {
        setLastSeenNotificationId(newestNotificationId.value);
    }
});

function normalize(value: string | undefined | null) {
    return String(value ?? '')
        .toLowerCase()
        .trim();
}

function fetchTelemetry() {
    if (telemetryInFlight || document.visibilityState === 'hidden') {
        return Promise.resolve();
    }

    telemetryInFlight = true;

    return fetch(toUrl(apiLatest()), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
        .then((response) => (response.ok ? response.json() : null))
        .then((payload: LatestPayload | null) => {
            if (!payload) return;

            const power = Math.max(0, Number(payload.power ?? 0));
            const current = displayCurrent(payload.current);
            const updatedAt = payload.updated_at
                ? Date.parse(payload.updated_at)
                : 0;
            const online =
                Number.isFinite(updatedAt) && updatedAt > 0
                    ? Date.now() - updatedAt < 5 * 60 * 1000
                    : false;

            telemetry.value = {
                power: `${power.toFixed(1)}W`,
                current: `${current.toFixed(3)}A`,
                online,
            };
        })
        .catch(() => {
            telemetry.value.online = false;
        })
        .finally(() => {
            telemetryInFlight = false;
        });
}

function fetchNotifications() {
    if (notificationsInFlight || document.visibilityState === 'hidden') {
        return Promise.resolve();
    }

    notificationsInFlight = true;

    return fetch(
        toUrl(
            notificationsLatest({
                query: {
                    limit: 10,
                },
            }),
        ),
        {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    )
        .then((response) => (response.ok ? response.json() : null))
        .then((payload) => {
            notifications.value = Array.isArray(payload?.notifications)
                ? payload.notifications
                : [];
        })
        .catch(() => {
            if (!notifications.value.length) {
                notifications.value = [];
            }
        })
        .finally(() => {
            notificationsInFlight = false;
        });
}

function relativeTime(value?: string | null) {
    if (!value) return 'Just now';
    const timestamp = Date.parse(value);
    if (!Number.isFinite(timestamp)) return 'Just now';

    const diffSeconds = Math.max(
        0,
        Math.floor((Date.now() - timestamp) / 1000),
    );
    if (diffSeconds < 10) return 'Just now';
    if (diffSeconds < 60) return `${diffSeconds}s ago`;
    if (diffSeconds < 3600) return `${Math.floor(diffSeconds / 60)} min ago`;
    if (diffSeconds < 86400) return `${Math.floor(diffSeconds / 3600)} h ago`;
    return `${Math.floor(diffSeconds / 86400)} d ago`;
}

function toneClasses(level?: string) {
    if (level === 'error') return 'bg-red-500/15 text-red-300 ring-red-500/20';
    if (level === 'warning') {
        return 'bg-amber-500/15 text-amber-300 ring-amber-500/20';
    }
    if (level === 'success') {
        return 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/20';
    }
    return 'bg-sky-500/15 text-sky-300 ring-sky-500/20';
}

function setSearchOpen(nextOpen: boolean) {
    searchOpen.value = nextOpen;
    if (!nextOpen) {
        activeIndex.value = 0;
    }
}

function recentItems(): SearchResult[] {
    try {
        const values = JSON.parse(
            window.localStorage.getItem(searchRecentKey) || '[]',
        ) as string[];

        return values.map((label) => ({
            label: `Recent: ${label}`,
            keywords: normalize(label),
            kind: 'href',
            href: undefined,
            onSelect: () => {
                searchQuery.value = label;
                setSearchOpen(true);
                void nextTick(() => searchInput.value?.focus());
            },
        }));
    } catch {
        return [];
    }
}

function saveRecent(label: string) {
    try {
        const current = JSON.parse(
            window.localStorage.getItem(searchRecentKey) || '[]',
        ) as string[];

        const next = current.filter((item) => item !== label);
        next.unshift(label);
        window.localStorage.setItem(
            searchRecentKey,
            JSON.stringify(next.slice(0, 8)),
        );
    } catch {
        // Ignore storage failures.
    }
}

function collectCurrentTargets(): SearchResult[] {
    const selectors = [
        'main h1',
        'main h2',
        'main h3',
        'main [data-search-label]',
    ];

    const seen = new Set<string>();

    const results: SearchResult[] = [];

    Array.from(
        document.querySelectorAll<HTMLElement>(selectors.join(',')),
    ).forEach((element) => {
        let label =
            element.getAttribute('data-search-label') ||
            element.textContent ||
            '';
        label = label.replace(/\s+/g, ' ').trim();

        if (!label) return;
        if (label.length > 80) label = `${label.slice(0, 80)}...`;

        const key = normalize(label);
        if (seen.has(key)) return;
        seen.add(key);

        results.push({
            label: `Find: ${label}`,
            keywords: key,
            kind: 'find',
            onSelect: () => {
                element.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                });
                element.classList.add('ring-2', 'ring-primary/50');
                window.setTimeout(() => {
                    element.classList.remove('ring-2', 'ring-primary/50');
                }, 1200);
            },
        });
    });

    return results;
}

function executeSearchResult(item?: SearchResult) {
    if (!item) return;

    const cleanLabel = item.label
        .replace(/^Recent:\s*/, '')
        .replace(/^Find:\s*/, '');
    saveRecent(cleanLabel);

    if (item.onSelect) {
        item.onSelect();
        return;
    }

    if (item.kind === 'href' && item.href) {
        window.location.href = item.href;
        return;
    }

    if (item.kind === 'panel' && item.panel === 'notifications') {
        notificationsOpen.value = true;
        accountsOpen.value = false;
        setSearchOpen(false);
        return;
    }

    if (item.kind === 'panel' && item.panel === 'accounts') {
        accountsOpen.value = true;
        notificationsOpen.value = false;
        setSearchOpen(false);
    }
}

function handleSearchKeydown(event: KeyboardEvent) {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (searchResults.value.length > 0) {
            activeIndex.value =
                (activeIndex.value + 1) % searchResults.value.length;
        }
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (searchResults.value.length > 0) {
            activeIndex.value =
                (activeIndex.value - 1 + searchResults.value.length) %
                searchResults.value.length;
        }
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        executeSearchResult(
            searchResults.value[activeIndex.value] || searchResults.value[0],
        );
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        setSearchOpen(false);
        accountsOpen.value = false;
        notificationsOpen.value = false;
    }
}

function lastSeenNotificationId() {
    const raw = Number(
        window.localStorage.getItem(notificationsSeenKey) || '0',
    );
    return Number.isFinite(raw) ? raw : 0;
}

function setLastSeenNotificationId(value: number) {
    window.localStorage.setItem(notificationsSeenKey, String(value));
}

function handleDocumentClick(event: MouseEvent) {
    const target = event.target as Node | null;
    if (!target) return;

    if (searchRoot.value && !searchRoot.value.contains(target)) {
        setSearchOpen(false);
    }

    if (accountsRoot.value && !accountsRoot.value.contains(target)) {
        accountsOpen.value = false;
    }

    if (notificationsRoot.value && !notificationsRoot.value.contains(target)) {
        notificationsOpen.value = false;
    }
}

function handleGlobalKeydown(event: KeyboardEvent) {
    const isShortcut =
        (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k';

    if (!isShortcut) {
        if (event.key === 'Escape') {
            accountsOpen.value = false;
            notificationsOpen.value = false;
            setSearchOpen(false);
        }
        return;
    }

    event.preventDefault();
    setSearchOpen(true);
    void nextTick(() => {
        searchInput.value?.focus();
        searchInput.value?.select();
    });
}

onMounted(() => {
    void fetchTelemetry();
    void fetchNotifications();

    telemetryTimer = window.setInterval(() => {
        void fetchTelemetry();
    }, 1000);

    notificationsTimer = window.setInterval(() => {
        void fetchNotifications();
    }, 15000);

    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleGlobalKeydown);
});

onBeforeUnmount(() => {
    if (telemetryTimer) window.clearInterval(telemetryTimer);
    if (notificationsTimer) window.clearInterval(notificationsTimer);

    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleGlobalKeydown);
});
</script>

<template>
    <header
        class="grid h-16 shrink-0 grid-cols-[auto_1fr_auto] items-center gap-3 px-2 pb-2 lg:px-4"
    >
        <div class="flex items-center">
            <span class="hidden h-9 w-9 lg:inline-block" aria-hidden="true" />
        </div>

        <div ref="searchRoot" class="hidden justify-center sm:flex">
            <div class="relative w-full max-w-3xl">
                <Search
                    class="absolute top-1/2 left-3.5 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    ref="searchInput"
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search pages (Dashboard, Power Strip, Settings...)"
                    autocomplete="off"
                    class="light-outline h-11 w-full rounded-2xl bg-card pr-4 pl-10 text-sm text-foreground placeholder:text-muted-foreground/50 focus:ring-1 focus:ring-primary/30 focus:outline-none"
                    @focus="setSearchOpen(true)"
                    @input="setSearchOpen(true)"
                    @keydown="handleSearchKeydown"
                />
                <span
                    class="pointer-events-none absolute top-1/2 right-3.5 hidden -translate-y-1/2 rounded-lg bg-background px-2 py-1 text-[10px] font-semibold text-muted-foreground lg:inline-flex"
                >
                    Ctrl/⌘ K
                </span>

                <div
                    :class="{ hidden: !searchOpen }"
                    class="absolute top-[calc(100%+0.5rem)] right-0 left-0 z-50 overflow-hidden rounded-2xl border border-primary/35 bg-card shadow-2xl ring-1 shadow-black/60 ring-primary/25 outline outline-1 outline-border/50"
                >
                    <div
                        class="border-b border-border/20 px-3 py-2 text-[11px] text-muted-foreground"
                    >
                        Command Palette
                    </div>
                    <div class="max-h-[22rem] overflow-y-auto p-1.5">
                        <div
                            v-if="searchResults.length === 0"
                            class="px-3 py-3 text-sm text-muted-foreground"
                        >
                            No matches.
                        </div>

                        <button
                            v-for="(result, index) in searchResults"
                            :key="`${result.label}-${index}`"
                            :class="
                                index === activeIndex
                                    ? 'bg-primary/15 text-foreground'
                                    : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                            "
                            class="flex w-full items-center rounded-xl px-3 py-2 text-left text-sm transition"
                            @click="executeSearchResult(result)"
                        >
                            <span>{{ result.label }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-1.5">
            <div
                class="light-outline-soft hidden items-center gap-2 rounded-2xl bg-card px-3 py-2 text-xs text-muted-foreground ring-1 ring-border/30 lg:inline-flex"
            >
                <span
                    :class="telemetry.online ? 'bg-emerald-400' : 'bg-red-400'"
                    class="h-2 w-2 rounded-full"
                />
                <span class="font-semibold text-foreground tabular-nums">
                    {{ telemetry.power }}
                </span>
                <span class="tabular-nums">{{ telemetry.current }}</span>
            </div>

            <ThemeModeSwitcher
                compact
                button-class="h-9 w-9 rounded-2xl text-muted-foreground transition hover:text-foreground"
            />

            <div
                v-if="user?.role === 'admin'"
                ref="accountsRoot"
                class="relative"
            >
                <button
                    class="light-outline-soft inline-flex h-9 w-9 items-center justify-center rounded-2xl text-muted-foreground transition hover:text-foreground"
                    title="Accounts"
                    :aria-expanded="accountsOpen ? 'true' : 'false'"
                    @click.stop="accountsOpen = !accountsOpen"
                >
                    <Users class="h-[18px] w-[18px]" />
                    <span
                        v-if="(accountsSummary?.pending_requests ?? 0) > 0"
                        class="absolute -top-1 -right-1 min-w-[1.15rem] rounded-full bg-amber-400 px-1.5 py-0.5 text-center text-[10px] leading-none font-bold text-background"
                    >
                        {{ accountsSummary?.pending_requests }}
                    </span>
                    <span
                        v-else-if="(accountsSummary?.blocked ?? 0) > 0"
                        class="absolute -top-1 -right-1 min-w-[1.15rem] rounded-full bg-red-400 px-1.5 py-0.5 text-center text-[10px] leading-none font-bold text-background"
                    >
                        {{ accountsSummary?.blocked }}
                    </span>
                </button>

                <div
                    v-if="accountsOpen"
                    class="light-outline-strong absolute top-[calc(100%+0.75rem)] right-0 z-[95] w-[min(24rem,calc(100vw-1.5rem))] overflow-hidden rounded-3xl border border-border/50 bg-card shadow-2xl ring-1 shadow-black/40 ring-border/40"
                >
                    <div class="border-b border-border/30 px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold">Accounts</p>
                                <p class="text-[11px] text-muted-foreground">
                                    Manage roles, guest expiry, and blocked
                                    users.
                                </p>
                            </div>
                            <span
                                class="light-outline-soft inline-flex rounded-full bg-background px-2.5 py-1 text-[10px] font-semibold tracking-[0.16em] text-muted-foreground uppercase ring-1 ring-border/40"
                            >
                                Admin
                            </span>
                        </div>
                    </div>
                    <div class="grid gap-2 p-3 sm:grid-cols-3">
                        <div
                            class="light-outline rounded-2xl bg-background px-3 py-3 ring-1 ring-border/30"
                        >
                            <p
                                class="text-[10px] tracking-[0.14em] text-muted-foreground uppercase"
                            >
                                Accounts
                            </p>
                            <p class="mt-1 text-lg font-semibold tabular-nums">
                                {{ accountsSummary?.total ?? 0 }}
                            </p>
                        </div>
                        <div
                            class="light-outline rounded-2xl bg-background px-3 py-3 ring-1 ring-border/30"
                        >
                            <p
                                class="text-[10px] tracking-[0.14em] text-muted-foreground uppercase"
                            >
                                Guests
                            </p>
                            <p class="mt-1 text-lg font-semibold tabular-nums">
                                {{ accountsSummary?.active_guests ?? 0 }}
                            </p>
                        </div>
                        <div
                            class="light-outline rounded-2xl bg-background px-3 py-3 ring-1 ring-border/30"
                        >
                            <p
                                class="text-[10px] tracking-[0.14em] text-muted-foreground uppercase"
                            >
                                Pending
                            </p>
                            <p class="mt-1 text-lg font-semibold tabular-nums">
                                {{ accountsSummary?.pending_requests ?? 0 }}
                            </p>
                        </div>
                    </div>
                    <div
                        class="border-t border-border/30 bg-background/70 px-3 py-3"
                    >
                        <a
                            :href="toUrl(accountsIndex())"
                            class="light-outline inline-flex w-full items-center justify-center rounded-2xl bg-card px-4 py-2.5 text-sm font-medium text-foreground ring-1 ring-border/40 transition hover:bg-muted/40"
                        >
                            Open account center
                        </a>
                    </div>
                </div>
            </div>

            <div ref="notificationsRoot" class="relative">
                <button
                    class="light-outline-soft inline-flex h-9 w-9 items-center justify-center rounded-2xl text-muted-foreground transition hover:text-foreground"
                    title="Notifications"
                    :aria-expanded="notificationsOpen ? 'true' : 'false'"
                    @click.stop="notificationsOpen = !notificationsOpen"
                >
                    <Bell class="h-[18px] w-[18px]" />
                    <span
                        v-if="unseenNotifications > 0"
                        class="absolute -top-1 -right-1 min-w-[1.15rem] rounded-full bg-amber-400 px-1.5 py-0.5 text-center text-[10px] leading-none font-bold text-background"
                    >
                        {{
                            unseenNotifications > 9 ? '9+' : unseenNotifications
                        }}
                    </span>
                </button>

                <div
                    v-if="notificationsOpen"
                    class="light-outline-strong absolute top-[calc(100%+0.75rem)] right-0 z-[95] w-[min(26rem,calc(100vw-1.5rem))] overflow-hidden rounded-3xl border border-border/50 bg-card shadow-2xl ring-1 shadow-black/40 ring-border/40"
                >
                    <div class="border-b border-border/30 px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold">
                                    Notifications
                                </p>
                                <p class="text-[11px] text-muted-foreground">
                                    Latest 10 events, refreshed live.
                                </p>
                            </div>
                            <span
                                class="light-outline-soft inline-flex rounded-full bg-background px-2.5 py-1 text-[10px] font-semibold tracking-[0.16em] text-muted-foreground uppercase ring-1 ring-border/40"
                            >
                                Live
                            </span>
                        </div>
                    </div>

                    <div class="max-h-[28rem] overflow-y-auto p-2">
                        <div
                            v-if="notifications.length === 0"
                            class="px-2 py-6 text-center text-sm text-muted-foreground"
                        >
                            No notifications yet. Important system events will
                            appear here.
                        </div>

                        <article
                            v-for="item in notifications"
                            :key="item.id || item.created_at || item.title"
                            class="light-outline rounded-2xl bg-background px-3 py-3 ring-1 ring-border/30"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            :class="toneClasses(item.level)"
                                            class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold tracking-[0.14em] uppercase ring-1"
                                        >
                                            {{ item.level || 'info' }}
                                        </span>
                                        <span
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            {{ relativeTime(item.created_at) }}
                                        </span>
                                    </div>
                                    <p
                                        class="mt-2 text-sm font-semibold text-foreground"
                                    >
                                        {{ item.title }}
                                    </p>
                                    <p
                                        v-if="item.message"
                                        class="mt-1.5 text-xs leading-5 text-muted-foreground"
                                    >
                                        {{ item.message }}
                                    </p>
                                    <a
                                        v-if="item.action_url"
                                        :href="item.action_url"
                                        class="mt-3 inline-flex text-xs font-medium text-primary transition hover:opacity-80"
                                    >
                                        Open
                                    </a>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div
                        class="border-t border-border/30 bg-background/70 px-3 py-3"
                    >
                        <a
                            :href="toUrl(notificationsIndex())"
                            class="light-outline inline-flex w-full items-center justify-center rounded-2xl bg-card px-4 py-2.5 text-sm font-medium text-foreground ring-1 ring-border/40 transition hover:bg-muted/40"
                        >
                            Open full notification history
                        </a>
                    </div>
                </div>
            </div>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        class="light-outline-soft ml-1 flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-card text-sm font-semibold"
                    >
                        {{ getInitials(user?.name) || 'U' }}
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-56 rounded-3xl p-2"
                    align="end"
                    :side-offset="8"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
