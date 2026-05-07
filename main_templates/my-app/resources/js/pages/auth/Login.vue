<script setup lang="ts">
import { onMounted } from 'vue';
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import { AlertTriangle } from 'lucide-vue-next';
import BrandLogoImage from '@/components/BrandLogoImage.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import ThemeModeSwitcher from '@/components/ThemeModeSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Toaster } from '@/components/ui/sonner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

const props = defineProps<{
    status?: string;
    sessionReplaced: boolean;
    canResetPassword: boolean;
    canRegister: boolean;
}>();

const querySessionReplaced =
    typeof window !== 'undefined' &&
    new URLSearchParams(window.location.search).has('session_replaced');

const sessionReplacedNotice = computed(
    () => props.sessionReplaced || querySessionReplaced,
);

const statusMessage = computed(() =>
    props.status ??
    (sessionReplacedNotice.value
        ? 'Your account was signed in on another device. Please sign in again.'
        : undefined),
);

const toastId = 'login-status-toast';

function showStatusToast(): void {
    if (!statusMessage.value) {
        return;
    }

    const isSessionReplacement = sessionReplacedNotice.value;

    const toastFn = isSessionReplacement ? toast.warning : toast.info;

    toastFn(
        isSessionReplacement ? 'Session replaced' : 'Notice',
        {
            id: toastId,
            description: statusMessage.value,
            duration: 6000,
            action: isSessionReplacement
                ? {
                      label: 'Continue',
                      onClick: () => {
                          window.requestAnimationFrame(() => {
                              const email = document.getElementById(
                                  'email',
                              ) as HTMLInputElement | null;
                              email?.focus();
                          });
                      },
                  }
                : undefined,
        },
    );
}

onMounted(() => {
    showStatusToast();
});
</script>

<template>
    <Head title="Log in" />

    <Toaster
        position="top-right"
        :expand="false"
        :visible-toasts="1"
        :toast-options="{
            class: 'w-[22rem] rounded-2xl border border-border/50 bg-[rgba(20,20,22,0.98)] shadow-2xl shadow-black/30 backdrop-blur-md',
            descriptionClass: 'text-[12px] leading-5 text-muted-foreground',
            classes: {
                title: 'text-sm font-semibold text-foreground',
                description: 'text-[12px] leading-5 text-muted-foreground',
                warning:
                    'border-amber-500/40 bg-[rgba(30,22,10,0.98)] text-amber-50',
                info: 'border-sky-500/40 bg-[rgba(12,20,30,0.98)] text-sky-50',
                success:
                    'border-emerald-500/40 bg-[rgba(14,28,18,0.98)] text-emerald-50',
                error: 'border-red-500/40 bg-[rgba(34,16,16,0.98)] text-red-50',
            },
        }"
    />

    <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-gray-50 via-gray-100 to-gray-200 text-slate-800 dark:from-[#232323] dark:via-[#1f1f1f] dark:to-[#171717] dark:text-slate-200 selection:bg-emerald-500/30">
        <div class="absolute inset-0 w-full overflow-hidden">
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#00000009_1px,transparent_1px),linear-gradient(to_bottom,#00000009_1px,transparent_1px)] dark:bg-[linear-gradient(to_right,#ffffff05_1px,transparent_1px),linear-gradient(to_bottom,#ffffff05_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_110%)]"></div>
        </div>

        <div class="relative mx-auto flex min-h-screen w-full max-w-6xl flex-col px-4 sm:px-6 lg:px-8">
            <header class="mt-8 rounded-full border border-slate-200/60 dark:border-gray-700/50 bg-white/70 dark:bg-gray-700/60 py-3 px-6 shadow-md dark:shadow-xl backdrop-blur-xl">
                <nav class="flex items-center gap-4">
                    <Link href="/" class="flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-lg bg-white ring-1 ring-black/5 dark:bg-white/95 dark:shadow-emerald-500/20">
                            <BrandLogoImage class="h-full w-full object-cover" />
                        </div>
                        <span class="font-semibold text-slate-900 dark:text-white tracking-wide">PulseNode</span>
                    </Link>

                    <div class="flex flex-1 items-center justify-center gap-10 text-[13px] font-medium text-slate-500 dark:text-slate-400">
                        <a href="#" class="hover:text-slate-900 dark:hover:text-white transition-colors">Devices</a>
                        <a href="#" class="hover:text-slate-900 dark:hover:text-white transition-colors">Energy Plans</a>
                        <a href="https://github.com/Copernicus12/PulseNode" target="_blank" rel="noreferrer" class="hover:text-slate-900 dark:hover:text-white transition-colors">Docs</a>
                    </div>

                    <div class="flex items-center gap-3 text-[13px] font-medium">
                        <ThemeModeSwitcher
                            compact
                            button-class="border border-slate-200 dark:border-gray-700/60 bg-white dark:bg-gray-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-gray-700 rounded-full px-2.5 py-2"
                        />

                        <div class="w-px h-5 bg-slate-300 dark:bg-white/10 mx-1"></div>
                        <Link href="/" class="rounded-full border border-slate-300 dark:border-white/10 bg-slate-100 dark:bg-white/5 px-5 py-2 text-slate-800 dark:text-white transition hover:bg-slate-200 dark:hover:bg-white/10">
                            Home
                        </Link>
                    </div>
                </nav>
            </header>

            <main class="flex flex-1 items-center py-10 lg:py-16">
                <div class="grid w-full items-stretch gap-8 lg:grid-cols-2">
                    <section class="hidden lg:flex rounded-[2.5rem] border border-slate-200 dark:border-gray-700/50 bg-white/60 dark:bg-gray-700/60 p-10 shadow-xl backdrop-blur-sm relative overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/10 via-transparent to-transparent"></div>
                        <div class="pointer-events-none absolute -top-20 -right-20 h-56 w-56 rounded-full bg-emerald-500/10 blur-3xl"></div>
                        <div class="relative z-10 flex flex-col justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-[0.3em] text-slate-500 dark:text-slate-400">Secure Access</p>
                                <h1 class="mt-4 text-4xl font-medium tracking-tight text-slate-900 dark:text-white leading-tight">
                                    Welcome back<br />to PulseNode
                                </h1>
                                <p class="mt-5 text-slate-600 dark:text-slate-400 max-w-md">
                                    Log in to control relays, monitor real-time telemetry, and review energy trends from your ESP32 network.
                                </p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 dark:border-gray-700/50 bg-white/80 dark:bg-gray-800/80 p-5">
                                <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400">Quick access</p>
                                <div class="mt-2 flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                    Sign in to manage your account and continue to dashboard control.
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[2.5rem] border border-slate-200 dark:border-gray-700/50 bg-white/70 dark:bg-gray-700/60 p-6 sm:p-8 shadow-xl dark:shadow-2xl backdrop-blur-sm">
                        <div class="mb-6">
                            <h2 class="text-2xl sm:text-3xl font-semibold text-slate-900 dark:text-white">Log in</h2>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Use your account credentials to access the dashboard.</p>
                        </div>

                        <Form
                            v-bind="store.form()"
                            :reset-on-success="['password']"
                            v-slot="{ errors, processing }"
                            class="flex flex-col gap-5"
                        >
                            <div class="grid gap-2">
                                <Label for="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autofocus
                                    :tabindex="1"
                                    autocomplete="email"
                                    placeholder="email@example.com"
                                    class="dark:border-gray-600 dark:bg-gray-800/70"
                                />
                                <InputError :message="errors.email" />
                            </div>

                            <div
                                v-if="
                                    typeof errors.email === 'string' &&
                                    errors.email.includes(
                                        'waiting for admin approval',
                                    )
                                "
                                class="rounded-2xl border border-amber-500/30 bg-amber-500/12 p-4 shadow-[0_0_0_1px_rgba(245,158,11,0.12)]"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-amber-200"
                                    >
                                        <AlertTriangle class="h-4 w-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm font-semibold text-amber-50"
                                        >
                                            Request pending approval
                                        </p>
                                        <p
                                            class="mt-1 text-sm leading-5 text-amber-100/90"
                                        >
                                            Your account request is waiting for
                                            an administrator. You cannot log in
                                            until it is approved.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else-if="
                                    typeof errors.email === 'string' &&
                                    errors.email.includes('declined')
                                "
                                class="rounded-2xl border border-red-500/30 bg-red-500/12 p-4 shadow-[0_0_0_1px_rgba(239,68,68,0.12)]"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-500/15 text-red-200"
                                    >
                                        <AlertTriangle class="h-4 w-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm font-semibold text-red-50"
                                        >
                                            Request declined
                                        </p>
                                        <p
                                            class="mt-1 text-sm leading-5 text-red-100/90"
                                        >
                                            This account request was declined.
                                            Please contact an administrator if
                                            you need access.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <div class="flex items-center justify-between">
                                    <Label for="password">Password</Label>
                                    <TextLink v-if="canResetPassword" :href="request()" class="text-sm" :tabindex="5">
                                        Forgot password?
                                    </TextLink>
                                </div>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    :tabindex="2"
                                    autocomplete="current-password"
                                    placeholder="Password"
                                    class="dark:border-gray-600 dark:bg-gray-800/70"
                                />
                                <InputError :message="errors.password" />
                            </div>

                            <div class="flex items-center justify-between">
                                <Label for="remember" class="flex items-center space-x-3">
                                    <Checkbox id="remember" name="remember" :tabindex="3" />
                                    <span>Remember me</span>
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                class="mt-2 w-full rounded-full bg-slate-900 text-white hover:bg-slate-800 dark:bg-white dark:text-[#0D0D12] dark:hover:bg-slate-200"
                                :tabindex="4"
                                :disabled="processing"
                                data-test="login-button"
                            >
                                <Spinner v-if="processing" />
                                Log in to Dashboard
                            </Button>

                            <div v-if="canRegister" class="text-center text-sm text-slate-600 dark:text-slate-400">
                                Don&apos;t have an account?
                                <TextLink :href="register()" :tabindex="5">Request access</TextLink>
                            </div>
                        </Form>
                    </section>
                </div>
            </main>
        </div>
    </div>
</template>
