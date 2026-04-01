<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SettingsLayout from '@/layouts/settings/Layout.vue';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
};

defineProps<Props>();

const page = usePage();
const user = page.props.auth.user as {
    name: string;
    email: string;
    email_verified_at?: string | null;
};
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile Settings</h1>

    <SettingsLayout>
        <div class="space-y-6">
            <Heading
                title="Profile settings"
                description="Update the basic information used by your account."
            />

            <Card class="border-border/40 shadow-none">
                <CardHeader class="gap-2 p-6">
                    <CardTitle class="text-xl">Personal information</CardTitle>
                    <CardDescription class="text-sm leading-6">
                        Change your name and email address here.
                    </CardDescription>
                </CardHeader>

                <CardContent class="p-6 pt-0">
                    <Form
                        v-bind="ProfileController.update.form()"
                        class="space-y-6"
                        v-slot="{ errors, processing, recentlySuccessful }"
                    >
                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="name">Full name</Label>
                                <Input
                                    id="name"
                                    class="h-11 rounded-xl"
                                    name="name"
                                    :default-value="user.name"
                                    required
                                    autocomplete="name"
                                    placeholder="Full name"
                                />
                                <InputError :message="errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    class="h-11 rounded-xl"
                                    name="email"
                                    :default-value="user.email"
                                    required
                                    autocomplete="username"
                                    placeholder="Email address"
                                />
                                <InputError :message="errors.email" />
                            </div>
                        </div>

                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge
                                    :variant="
                                        user.email_verified_at
                                            ? 'default'
                                            : 'secondary'
                                    "
                                    class="rounded-full px-3 py-1"
                                >
                                    {{
                                        user.email_verified_at
                                            ? 'Email verified'
                                            : 'Email verification needed'
                                    }}
                                </Badge>
                                <span class="text-sm text-muted-foreground">
                                    {{ user.email }}
                                </span>
                            </div>

                            <div class="flex items-center gap-3">
                                <Transition
                                    enter-active-class="transition ease-in-out"
                                    enter-from-class="opacity-0"
                                    leave-active-class="transition ease-in-out"
                                    leave-to-class="opacity-0"
                                >
                                    <p
                                        v-show="recentlySuccessful"
                                        class="text-sm font-medium text-primary"
                                    >
                                        Saved.
                                    </p>
                                </Transition>

                                <Button
                                    :disabled="processing"
                                    class="rounded-xl px-5"
                                    data-test="update-profile-button"
                                >
                                    Save changes
                                </Button>
                            </div>
                        </div>
                    </Form>
                </CardContent>
            </Card>

            <Card
                v-if="mustVerifyEmail && !user.email_verified_at"
                class="border-amber-500/30 bg-amber-500/5 shadow-none"
            >
                <CardHeader class="gap-2 p-6">
                    <CardTitle class="text-base">Verify your email</CardTitle>
                    <CardDescription class="text-sm leading-6">
                        Send a new verification email if you have not confirmed
                        your address yet.
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-4 p-6 pt-0">
                    <p class="text-sm leading-6 text-muted-foreground">
                        Your email address is currently unverified.
                    </p>

                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            href="/email/verification-notification"
                            method="post"
                            as="button"
                            class="inline-flex h-10 items-center justify-center rounded-xl bg-primary px-4 text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
                        >
                            Resend verification email
                        </Link>

                        <p
                            v-if="status === 'verification-link-sent'"
                            class="text-sm font-medium text-emerald-600 dark:text-emerald-400"
                        >
                            Verification link sent.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <DeleteUser />
        </div>
    </SettingsLayout>
</template>
