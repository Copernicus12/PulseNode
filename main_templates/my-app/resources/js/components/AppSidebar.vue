<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { KeyRound, Palette, ShieldCheck, UserRound } from 'lucide-vue-next';
import { computed } from 'vue';
import { Sidebar, SidebarContent } from '@/components/ui/sidebar';
import type { SidebarProps } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as accountsIndex } from '@/routes/accounts';
import { edit as editAppearance } from '@/routes/appearance';
import { index as batteryIndex } from '@/routes/battery';
import devices from '@/routes/devices';
import { index as historyIndex } from '@/routes/history';
import { logout } from '@/routes/index';
import powerStrip from '@/routes/power-strip';
import { edit as editProfile } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import { type NavItem } from '@/types';

withDefaults(
    defineProps<{
        collapsible?: SidebarProps['collapsible'];
    }>(),
    {
        collapsible: 'icon',
    },
);

const page = usePage();
const user = computed(
    () =>
        page.props.auth.user as {
            role?: string;
        },
);

const { isCurrentUrl } = useCurrentUrl();

const devicesNavItems: NavItem[] = [
    {
        title: 'Overview',
        href: devices.index(),
    },
    {
        title: 'Profiles',
        href: devices.profiles.index(),
    },
    {
        title: 'Plans',
        href: devices.plans.index(),
    },
    {
        title: 'Activity',
        href: devices.activity.index(),
    },
];

const devicesGroupActive = computed(() =>
    devicesNavItems.some((item) => isCurrentUrl(item.href)),
);

const settingsNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: editProfile(),
        icon: UserRound,
    },
    {
        title: 'Password',
        href: editPassword(),
        icon: KeyRound,
    },
    {
        title: 'Two-Factor Auth',
        href: show(),
        icon: ShieldCheck,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: Palette,
    },
];

const settingsGroupActive = computed(() =>
    settingsNavItems.some((item) => isCurrentUrl(item.href)),
);

const handleLogout = () => {
    router.flushAll();
};
</script>

<template>
    <Sidebar
        :collapsible="collapsible"
        variant="floating"
        class="[--sidebar-width:264px]"
    >
        <SidebarContent class="flex-1 gap-0 overflow-visible">
            <div class="px-3 pt-3 pb-8">
                <span
                    class="text-xl font-bold tracking-tight"
                    style="font-weight: 700"
                    >PulseNode</span
                >
            </div>

            <nav class="flex-1 space-y-1.5 overflow-y-auto lg:overflow-visible">
                <a
                    :href="toUrl(dashboard())"
                    :class="
                        isCurrentUrl(dashboard())
                            ? 'bg-primary font-semibold text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                    "
                    class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[14px] transition"
                >
                    <svg
                        class="h-[18px] w-[18px]"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path
                            d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"
                        />
                        <polyline points="9 22 9 12 15 12 15 22" />
                    </svg>
                    <span>Dashboard</span>
                </a>

                <a
                    :href="toUrl(powerStrip.index())"
                    :class="
                        isCurrentUrl(powerStrip.index())
                            ? 'bg-primary font-semibold text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                    "
                    class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[14px] transition"
                >
                    <svg
                        class="h-[18px] w-[18px]"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            d="M5.127 3.502L5.25 3.5h9.5q.062 0 .123.002A2.25 2.25 0 0 0 12.75 2h-5.5a2.25 2.25 0 0 0-2.123 1.502M1 10.25A2.25 2.25 0 0 1 3.25 8h13.5A2.25 2.25 0 0 1 19 10.25v5.5A2.25 2.25 0 0 1 16.75 18H3.25A2.25 2.25 0 0 1 1 15.75zM3.25 6.5l-.123.002A2.25 2.25 0 0 1 5.25 5h9.5c.98 0 1.814.627 2.123 1.502L16.75 6.5z"
                        />
                    </svg>
                    <span>Power Strip</span>
                </a>

                <details
                    :open="devicesGroupActive"
                    :class="
                        devicesGroupActive
                            ? 'group relative rounded-2xl bg-primary/8 ring-1 ring-primary/20'
                            : 'group relative rounded-2xl'
                    "
                >
                    <summary
                        :class="
                            devicesGroupActive
                                ? 'font-semibold text-primary'
                                : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                        "
                        class="flex cursor-pointer list-none items-center gap-3 rounded-2xl px-4 py-3 text-[14px] transition [&::-webkit-details-marker]:hidden"
                    >
                        <svg
                            class="h-[18px] w-[18px]"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <rect x="2.5" y="4" width="14" height="11" rx="2" />
                            <path d="M7 19h5" />
                            <path d="M9.5 15v4" />
                            <rect x="17" y="7" width="4.5" height="9" rx="1" />
                            <circle cx="19.25" cy="14" r="0.6" />
                        </svg>
                        <span class="flex-1">My Devices</span>
                        <svg
                            class="h-4 w-4 transition group-open:rotate-180"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <polyline points="6 9 12 15 18 9" />
                        </svg>
                    </summary>

                    <div class="space-y-1 px-2 pb-2">
                        <a
                            v-for="item in devicesNavItems"
                            :key="item.title"
                            :href="toUrl(item.href)"
                            :class="
                                isCurrentUrl(item.href)
                                    ? 'bg-primary font-medium text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                            "
                            class="flex items-center rounded-xl px-3 py-2 text-[13px] transition lg:px-3.5 lg:py-2.5"
                        >
                            {{ item.title }}
                        </a>
                    </div>
                </details>

                <a
                    :href="toUrl(historyIndex())"
                    :class="
                        isCurrentUrl(historyIndex())
                            ? 'bg-primary font-semibold text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                    "
                    class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[14px] transition"
                >
                    <svg
                        class="h-[18px] w-[18px]"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <line x1="18" y1="20" x2="18" y2="10" />
                        <line x1="12" y1="20" x2="12" y2="4" />
                        <line x1="6" y1="20" x2="6" y2="14" />
                    </svg>
                    <span>History</span>
                </a>

                <a
                    v-if="user?.role === 'admin'"
                    :href="toUrl(accountsIndex())"
                    :class="
                        isCurrentUrl(accountsIndex())
                            ? 'bg-primary font-semibold text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                    "
                    class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[14px] transition"
                >
                    <svg
                        class="h-[18px] w-[18px]"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                    <span>Accounts</span>
                </a>

                <a
                    :href="toUrl(batteryIndex())"
                    :class="
                        isCurrentUrl(batteryIndex())
                            ? 'bg-primary font-semibold text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                    "
                    class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[14px] transition"
                >
                    <svg
                        class="h-[18px] w-[18px]"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            fill-rule="evenodd"
                            clip-rule="evenodd"
                            d="M3.75 6.75a3 3 0 0 0-3 3v6a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3v-.037c.856-.174 1.5-.93 1.5-1.838v-2.25c0-.907-.644-1.664-1.5-1.837V9.75a3 3 0 0 0-3-3zm15 1.5a1.5 1.5 0 0 1 1.5 1.5v6a1.5 1.5 0 0 1-1.5 1.5h-15a1.5 1.5 0 0 1-1.5-1.5v-6a1.5 1.5 0 0 1 1.5-1.5zM4.5 9.75a.75.75 0 0 0-.75.75V15c0 .414.336.75.75.75H18a.75.75 0 0 0 .75-.75v-4.5a.75.75 0 0 0-.75-.75z"
                        />
                    </svg>
                    <span>Battery Level</span>
                </a>
            </nav>
        </SidebarContent>

        <div class="mt-4 space-y-1.5 border-t border-border/20 pt-4">
            <details
                :open="settingsGroupActive"
                :class="
                    settingsGroupActive
                        ? 'group relative rounded-2xl bg-primary/8 ring-1 ring-primary/20'
                        : 'group relative rounded-2xl'
                "
            >
                <summary
                    :class="
                        settingsGroupActive
                            ? 'font-semibold text-primary'
                            : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                    "
                    class="flex cursor-pointer list-none items-center gap-3 rounded-2xl px-4 py-3 text-sm transition [&::-webkit-details-marker]:hidden"
                >
                    <svg
                        class="h-[18px] w-[18px]"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <circle cx="12" cy="12" r="3" />
                        <path
                            d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"
                        />
                    </svg>
                    <span class="flex-1">Settings</span>
                    <svg
                        class="h-4 w-4 transition group-open:rotate-180"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <polyline points="6 9 12 15 18 9" />
                    </svg>
                </summary>

                <div class="space-y-1 px-2 pb-2">
                    <Link
                        v-for="item in settingsNavItems"
                        :key="item.title"
                        :href="item.href"
                        :class="
                            isCurrentUrl(item.href)
                                ? 'bg-primary font-medium text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                        "
                        class="flex items-center gap-3 rounded-xl px-3 py-2 text-[13px] transition lg:px-3.5 lg:py-2.5"
                    >
                        <component :is="item.icon" class="h-4 w-4 shrink-0" />
                        <span>{{ item.title }}</span>
                    </Link>
                </div>
            </details>

            <Link
                :href="logout()"
                as="button"
                class="flex w-full items-center gap-3 rounded-2xl px-4 py-3 text-left text-sm text-muted-foreground transition hover:bg-muted/50 hover:text-foreground"
                @click="handleLogout"
            >
                <svg
                    class="h-[18px] w-[18px]"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
                Log Out
            </Link>
        </div>
    </Sidebar>
    <slot />
</template>
