<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Languages, Monitor, Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardTitle,
} from '@/components/ui/card';
import { useAppearance } from '@/composables/useAppearance';
import { useInterfaceLanguage } from '@/composables/useInterfaceLanguage';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { AppPageProps, Appearance, InterfaceLanguage } from '@/types';

const page = usePage<AppPageProps<{ interfaceLanguage?: InterfaceLanguage }>>();
const { appearance, resolvedAppearance, updateAppearance } = useAppearance();
const { interfaceLanguage, updateInterfaceLanguage } = useInterfaceLanguage();

const themeOptions: Array<{
    value: Appearance;
    label: string;
    description: string;
    icon: typeof Sun;
}> = [
    {
        value: 'light',
        label: 'Light',
        description: 'Bright surfaces and higher daytime contrast.',
        icon: Sun,
    },
    {
        value: 'dark',
        label: 'Dark',
        description: 'Lower glare for dashboards and evening use.',
        icon: Moon,
    },
    {
        value: 'system',
        label: 'System',
        description: 'Follow the operating system preference automatically.',
        icon: Monitor,
    },
];

const languageOptions: Array<{
    value: InterfaceLanguage;
    label: string;
    nativeLabel: string;
    description: string;
}> = [
    {
        value: 'ro',
        label: 'Romanian',
        nativeLabel: 'Romana',
        description: 'Useful when you want Romanian as the selected interface language.',
    },
    {
        value: 'en',
        label: 'English',
        nativeLabel: 'English',
        description: 'Useful when you want English as the selected interface language.',
    },
];

const activeThemeLabel = computed(() =>
    themeOptions.find((option) => option.value === appearance.value)?.label ?? 'System',
);

const activeLanguageLabel = computed(() =>
    languageOptions.find((option) => option.value === interfaceLanguage.value)?.nativeLabel ?? 'English',
);

const currentLanguageFromServer = computed(() =>
    page.props.interfaceLanguage ?? interfaceLanguage.value,
);
</script>

<template>
    <Head title="Appearance settings" />

    <h1 class="sr-only">Appearance Settings</h1>

    <SettingsLayout>
        <div class="space-y-6 pl-1 lg:pl-3">
            <header class="relative overflow-hidden rounded-[28px] border border-border/40 bg-gradient-to-r from-primary/12 via-primary/6 to-transparent px-6 py-5 shadow-none">
                <div class="absolute inset-y-0 left-0 w-24 bg-gradient-to-r from-primary/10 to-transparent blur-2xl" />
                <div class="relative space-y-2">
                    <h2 class="bg-gradient-to-r from-foreground via-foreground to-primary bg-clip-text text-xl font-semibold tracking-tight text-transparent">
                        Appearance settings
                    </h2>
                    <p class="max-w-3xl text-sm text-muted-foreground">
                        Choose the active theme and the selected interface language for your PulseNode workspace.
                    </p>
                </div>
            </header>

            <div class="grid gap-4 md:grid-cols-2">
                <Card class="border-border/40 bg-card/70 shadow-none">
                    <CardContent class="flex items-center gap-4 p-5">
                        <div class="rounded-2xl bg-primary/12 p-3 text-primary">
                            <Monitor class="h-5 w-5" />
                        </div>
                        <div class="space-y-1">
                            <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                Active theme
                            </p>
                            <p class="text-xl font-semibold">
                                {{ activeThemeLabel }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Rendered as {{ resolvedAppearance }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-border/40 bg-card/70 shadow-none">
                    <CardContent class="flex items-center gap-4 p-5">
                        <div class="rounded-2xl bg-primary/12 p-3 text-primary">
                            <Languages class="h-5 w-5" />
                        </div>
                        <div class="space-y-1">
                            <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                Selected language
                            </p>
                            <p class="text-xl font-semibold">
                                {{ activeLanguageLabel }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Server locale {{ currentLanguageFromServer }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]">
                <Card class="border-border/40 bg-card/70 shadow-none">
                    <CardContent class="space-y-5 p-6">
                        <div class="space-y-2">
                            <CardTitle class="text-xl">Theme</CardTitle>
                            <CardDescription class="text-sm leading-6">
                                Pick how the interface should look across the dashboard, settings pages, and live monitoring screens.
                            </CardDescription>
                        </div>

                        <div class="grid grid-cols-1 justify-items-center gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <button
                                v-for="option in themeOptions"
                                :key="option.value"
                                type="button"
                                class="flex h-44 w-full max-w-[10.5rem] flex-col rounded-[24px] border p-4 text-left transition"
                                :class="
                                    appearance === option.value
                                        ? 'border-primary/35 bg-primary/8'
                                        : 'border-border/40 bg-background hover:border-primary/20 hover:bg-muted/20'
                                "
                                @click="updateAppearance(option.value)"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="rounded-2xl bg-primary/12 p-3 text-primary">
                                        <component :is="option.icon" class="h-5 w-5" />
                                    </div>
                                    <Badge
                                        v-if="appearance === option.value"
                                        class="rounded-full px-3 py-1"
                                    >
                                        Active
                                    </Badge>
                                </div>

                                <div class="mt-4 space-y-2">
                                    <p class="text-base font-semibold">{{ option.label }}</p>
                                    <p class="text-sm leading-6 text-muted-foreground line-clamp-3">
                                        {{ option.description }}
                                    </p>
                                </div>
                            </button>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-border/40 bg-card/70 shadow-none">
                    <CardContent class="space-y-5 p-6">
                        <div class="space-y-2">
                            <CardTitle class="text-xl">Language</CardTitle>
                            <CardDescription class="text-sm leading-6">
                                Keep the selected interface language ready for the app experience and future localized content.
                            </CardDescription>
                        </div>

                        <div class="space-y-4">
                            <button
                                v-for="option in languageOptions"
                                :key="option.value"
                                type="button"
                                class="flex w-full items-start justify-between gap-4 rounded-[24px] border p-4 text-left transition"
                                :class="
                                    interfaceLanguage === option.value
                                        ? 'border-primary/35 bg-primary/8'
                                        : 'border-border/40 bg-background hover:border-primary/20 hover:bg-muted/20'
                                "
                                @click="updateInterfaceLanguage(option.value)"
                            >
                                <div class="space-y-1.5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-base font-semibold">
                                            {{ option.nativeLabel }}
                                        </p>
                                        <span class="text-sm text-muted-foreground">
                                            {{ option.label }}
                                        </span>
                                    </div>
                                    <p class="text-sm leading-6 text-muted-foreground line-clamp-2">
                                        {{ option.description }}
                                    </p>
                                </div>

                                <Badge
                                    v-if="interfaceLanguage === option.value"
                                    class="rounded-full px-3 py-1"
                                >
                                    Selected
                                </Badge>
                            </button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </SettingsLayout>
</template>
