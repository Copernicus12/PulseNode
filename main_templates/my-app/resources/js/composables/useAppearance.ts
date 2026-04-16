import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

const appearanceStorageKey = 'appearance';
const mediaQueryString = '(prefers-color-scheme: dark)';

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia(mediaQueryString);
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    const storedAppearance = localStorage.getItem(appearanceStorageKey);

    if (
        storedAppearance === 'light' ||
        storedAppearance === 'dark' ||
        storedAppearance === 'system'
    ) {
        return storedAppearance;
    }

    return null;
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia(mediaQueryString).matches;
};

const appearance = ref<Appearance>('system');
const systemAppearance = ref<ResolvedAppearance>('light');

let hasInitializedTheme = false;

const resolveAppearance = (value: Appearance): ResolvedAppearance =>
    value === 'system' ? systemAppearance.value : value;

const syncAppearanceFromStorage = (nextAppearance?: Appearance | null) => {
    appearance.value = nextAppearance ?? getStoredAppearance() ?? 'system';
    systemAppearance.value = prefersDark() ? 'dark' : 'light';
};

export function updateTheme(value: Appearance): void {
    if (typeof document === 'undefined') {
        return;
    }

    const resolvedValue = resolveAppearance(value);
    const { documentElement } = document;

    documentElement.classList.toggle('dark', resolvedValue === 'dark');
    documentElement.style.colorScheme = resolvedValue;
    documentElement.dataset.appearance = value;
    documentElement.dataset.resolvedAppearance = resolvedValue;
}

const handleSystemThemeChange = () => {
    systemAppearance.value = prefersDark() ? 'dark' : 'light';
    updateTheme(appearance.value);
};

const handleStorageChange = (event: StorageEvent) => {
    if (event.key !== appearanceStorageKey) {
        return;
    }

    syncAppearanceFromStorage(event.newValue as Appearance | null);
    updateTheme(appearance.value);
};

export function initializeTheme(): void {
    if (typeof window === 'undefined' || hasInitializedTheme) {
        return;
    }

    hasInitializedTheme = true;
    syncAppearanceFromStorage();
    setCookie('appearance', appearance.value);
    updateTheme(appearance.value);

    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
    window.addEventListener('storage', handleStorageChange);
}

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        initializeTheme();
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() =>
        resolveAppearance(appearance.value),
    );

    function updateAppearance(value: Appearance) {
        appearance.value = value;
        systemAppearance.value = prefersDark() ? 'dark' : 'light';

        localStorage.setItem(appearanceStorageKey, value);

        setCookie('appearance', value);
        updateTheme(value);
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}
