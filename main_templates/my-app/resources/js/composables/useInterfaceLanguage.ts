import type { Ref } from 'vue';
import { onMounted, ref } from 'vue';
import type { InterfaceLanguage } from '@/types';

export type UseInterfaceLanguageReturn = {
    interfaceLanguage: Ref<InterfaceLanguage>;
    updateInterfaceLanguage: (value: InterfaceLanguage) => void;
};

const supportedLanguages: InterfaceLanguage[] = ['en', 'ro'];

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const normalizeLanguage = (value: string | null | undefined): InterfaceLanguage | null => {
    if (! value) {
        return null;
    }

    const normalized = value.toLowerCase().slice(0, 2);

    return supportedLanguages.includes(normalized as InterfaceLanguage)
        ? (normalized as InterfaceLanguage)
        : null;
};

const getStoredLanguage = (): InterfaceLanguage | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    return normalizeLanguage(localStorage.getItem('interface_language'));
};

const getDocumentLanguage = (): InterfaceLanguage | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    return normalizeLanguage(document.documentElement.lang);
};

const getBrowserLanguage = (): InterfaceLanguage => {
    if (typeof navigator === 'undefined') {
        return 'en';
    }

    return normalizeLanguage(navigator.language) ?? 'en';
};

const updateDocumentLanguage = (value: InterfaceLanguage): void => {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.lang = value === 'ro' ? 'ro-RO' : 'en';
};

export function initializeInterfaceLanguage(): void {
    const savedLanguage = getStoredLanguage();
    const language = savedLanguage ?? getDocumentLanguage() ?? getBrowserLanguage();

    updateDocumentLanguage(language);
}

const interfaceLanguage = ref<InterfaceLanguage>('en');

export function useInterfaceLanguage(): UseInterfaceLanguageReturn {
    onMounted(() => {
        const savedLanguage = getStoredLanguage();
        const language = savedLanguage ?? getDocumentLanguage() ?? getBrowserLanguage();

        interfaceLanguage.value = language;
        updateDocumentLanguage(language);
    });

    function updateInterfaceLanguage(value: InterfaceLanguage): void {
        interfaceLanguage.value = value;

        localStorage.setItem('interface_language', value);
        setCookie('interface_language', value);
        updateDocumentLanguage(value);
    }

    return {
        interfaceLanguage,
        updateInterfaceLanguage,
    };
}
