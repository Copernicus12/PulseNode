import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import os from 'node:os';
import { defineConfig } from 'vite';

function resolveDevBindHost() {
    const envBindHost = process.env.DEV_BIND_HOST;

    if (envBindHost) {
        return envBindHost;
    }

    return '127.0.0.1';
}

function resolveDevPublicHost() {
    const envHost = process.env.DEV_HOST;

    if (envHost) {
        return envHost;
    }

    const appUrl = process.env.APP_URL;

    if (appUrl) {
        try {
            return new URL(appUrl).hostname;
        } catch {
            // Ignore invalid values and fall back to auto-detection.
        }
    }

    for (const networkInterfaces of Object.values(os.networkInterfaces())) {
        for (const networkInterface of networkInterfaces ?? []) {
            if (networkInterface.family === 'IPv4' && !networkInterface.internal) {
                return networkInterface.address;
            }
        }
    }

    return '127.0.0.1';
}

const devBindHost = resolveDevBindHost();
const devPublicHost = resolveDevPublicHost();

export default defineConfig({
    server: {
        host: devBindHost,
        cors: true,
        hmr: {
            host: devPublicHost,
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.ts',
                'resources/js/power-strip-safety-guard.ts',
                'resources/js/power-strip-notifications.ts',
                'resources/js/relay-command-toast.ts',
                'resources/js/history-page.ts',
                'resources/js/notifications-page.ts',
                'resources/js/accounts-page.ts',
                'resources/js/schedules-toast.ts',
                'resources/js/schedules-page.ts',
            ],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
