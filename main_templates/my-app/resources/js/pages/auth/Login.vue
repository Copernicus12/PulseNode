<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import ThemeModeSwitcher from '@/components/ThemeModeSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <Head title="Log in" />

    <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-gray-50 via-gray-100 to-gray-200 text-slate-800 dark:from-[#232323] dark:via-[#1f1f1f] dark:to-[#171717] dark:text-slate-200 selection:bg-emerald-500/30">
        <div class="absolute inset-0 w-full overflow-hidden">
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#00000009_1px,transparent_1px),linear-gradient(to_bottom,#00000009_1px,transparent_1px)] dark:bg-[linear-gradient(to_right,#ffffff05_1px,transparent_1px),linear-gradient(to_bottom,#ffffff05_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_110%)]"></div>
        </div>

        <div class="relative mx-auto flex min-h-screen w-full max-w-6xl flex-col px-4 sm:px-6 lg:px-8">
            <header class="mt-8 rounded-full border border-slate-200/60 dark:border-gray-700/50 bg-white/70 dark:bg-gray-700/60 py-3 px-6 shadow-md dark:shadow-xl backdrop-blur-xl">
                <nav class="flex items-center justify-between">
                    <Link href="/" class="flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-600 dark:bg-emerald-500 dark:shadow-emerald-500/20">
                            <AppLogoIcon class="h-5 w-5 text-white" />
                        </div>
                        <span class="font-semibold text-slate-900 dark:text-white tracking-wide">PulseNode</span>
                    </Link>

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

                        <div v-if="status" class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-700 dark:text-emerald-300">
                            {{ status }}
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
