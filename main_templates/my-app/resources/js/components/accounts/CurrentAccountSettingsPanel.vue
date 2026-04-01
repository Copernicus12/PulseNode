<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    KeyRound,
    ShieldBan,
    ShieldCheck,
    UserRoundCog,
} from 'lucide-vue-next';
import { onUnmounted, reactive, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { disable, enable } from '@/routes/two-factor';

type CurrentUser = {
    name: string;
    email: string;
    email_verified_at?: string | null;
    must_verify_email: boolean;
    two_factor_enabled: boolean;
    requires_two_factor_confirmation: boolean;
};

const props = defineProps<{
    currentUser: CurrentUser;
    validationErrors: Record<string, string[]>;
    csrfToken: string;
    isSubmitting: boolean;
    profileAction: string;
    passwordAction: string;
    submitRequest: (url: string, formData: FormData) => Promise<boolean>;
}>();

const profileModalOpen = ref(false);
const passwordModalOpen = ref(false);
const securityModalOpen = ref(false);
const showSetupModal = ref(false);

const profileForm = reactive({
    name: props.currentUser.name,
    email: props.currentUser.email,
});

const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const twoFactorEnabled = ref(props.currentUser.two_factor_enabled);
const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();

watch(
    () => props.currentUser,
    (user) => {
        profileForm.name = user.name;
        profileForm.email = user.email;
        twoFactorEnabled.value = user.two_factor_enabled;
    },
    { deep: true },
);

onUnmounted(() => {
    clearTwoFactorAuthData();
});

function firstError(field: string): string | undefined {
    return props.validationErrors[field]?.[0];
}

async function submitProfile(): Promise<void> {
    const formData = new FormData();
    formData.set('_token', props.csrfToken);
    formData.set('_method', 'PATCH');
    formData.set('name', profileForm.name);
    formData.set('email', profileForm.email);

    const succeeded = await props.submitRequest(props.profileAction, formData);

    if (succeeded) {
        profileModalOpen.value = false;
    }
}

async function submitPassword(): Promise<void> {
    const formData = new FormData();
    formData.set('_token', props.csrfToken);
    formData.set('_method', 'PUT');
    formData.set('current_password', passwordForm.current_password);
    formData.set('password', passwordForm.password);
    formData.set('password_confirmation', passwordForm.password_confirmation);

    const succeeded = await props.submitRequest(props.passwordAction, formData);

    if (succeeded) {
        passwordForm.current_password = '';
        passwordForm.password = '';
        passwordForm.password_confirmation = '';
        passwordModalOpen.value = false;
    }
}

function handleTwoFactorEnable(): void {
    if (!props.currentUser.requires_two_factor_confirmation) {
        twoFactorEnabled.value = true;
    }

    showSetupModal.value = true;
}

function handleTwoFactorConfirmed(): void {
    twoFactorEnabled.value = true;
}

function handleTwoFactorDisabled(): void {
    twoFactorEnabled.value = false;
    clearTwoFactorAuthData();
}
</script>

<template>
    <Card class="border-border/40 shadow-none">
        <CardHeader class="gap-3 p-5 sm:p-6">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-2">
                    <UserRoundCog class="size-4 text-muted-foreground" />
                    <CardTitle class="text-lg tracking-tight">
                        Current account settings
                    </CardTitle>
                </div>

                <Badge
                    variant="outline"
                    class="w-fit rounded-full px-3 py-1 text-xs text-muted-foreground"
                >
                    Quick actions
                </Badge>
            </div>
            <CardDescription class="text-sm leading-6">
                Profile, password și two-factor sunt disponibile rapid aici,
                iar `Settings` rămâne pentru `Appearance` și `Billing`.
            </CardDescription>
        </CardHeader>

        <CardContent class="grid gap-4 p-5 pt-0 sm:p-6 sm:pt-0 lg:grid-cols-3">
            <div
                class="rounded-3xl border border-border/30 bg-card/60 p-5 transition hover:border-primary/20 hover:bg-card"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">Profile</p>
                        <p class="mt-1 text-xs leading-5 text-muted-foreground">
                            {{ currentUser.name }}
                        </p>
                        <p class="truncate text-xs leading-5 text-muted-foreground">
                            {{ currentUser.email }}
                        </p>
                    </div>

                    <Badge
                        :variant="
                            currentUser.email_verified_at
                                ? 'default'
                                : 'secondary'
                        "
                        class="shrink-0 rounded-full px-2.5 py-0.5 text-[10px]"
                    >
                        {{
                            currentUser.email_verified_at
                                ? 'Verified'
                                : 'Pending'
                        }}
                    </Badge>
                </div>

                <Button
                    class="mt-5 h-10 w-full rounded-xl"
                    @click="profileModalOpen = true"
                >
                    Edit profile
                </Button>
            </div>

            <div
                class="rounded-3xl border border-border/30 bg-card/60 p-5 transition hover:border-primary/20 hover:bg-card"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold">Password</p>
                        <p class="mt-1 text-xs leading-5 text-muted-foreground">
                            Update the current account password in one modal.
                        </p>
                    </div>

                    <KeyRound
                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                    />
                </div>

                <Button
                    class="mt-5 h-10 w-full rounded-xl"
                    variant="secondary"
                    @click="passwordModalOpen = true"
                >
                    Change password
                </Button>
            </div>

            <div
                class="rounded-3xl border border-border/30 bg-card/60 p-5 transition hover:border-primary/20 hover:bg-card"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold">Two-Factor Auth</p>
                        <p class="mt-1 text-xs leading-5 text-muted-foreground">
                            {{
                                twoFactorEnabled
                                    ? 'Security layer active for the current account.'
                                    : 'Enable 2FA for stronger sign-in protection.'
                            }}
                        </p>
                    </div>

                    <Badge
                        :variant="twoFactorEnabled ? 'default' : 'secondary'"
                        class="shrink-0 rounded-full px-2.5 py-0.5 text-[10px]"
                    >
                        {{ twoFactorEnabled ? 'Enabled' : 'Disabled' }}
                    </Badge>
                </div>

                <Button
                    class="mt-5 h-10 w-full rounded-xl"
                    variant="outline"
                    @click="securityModalOpen = true"
                >
                    Manage 2FA
                </Button>
            </div>
        </CardContent>
    </Card>

    <Dialog :open="profileModalOpen" @update:open="profileModalOpen = $event">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader class="space-y-2">
                <DialogTitle>Edit profile</DialogTitle>
                <DialogDescription>
                    Update the name and email used by your current account.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitProfile">
                <div class="grid gap-2">
                    <Label for="accounts-self-name">Full name</Label>
                    <Input
                        id="accounts-self-name"
                        v-model="profileForm.name"
                        type="text"
                        class="h-10 rounded-xl"
                        autocomplete="name"
                    />
                    <InputError :message="firstError('name')" />
                </div>

                <div class="grid gap-2">
                    <Label for="accounts-self-email">Email address</Label>
                    <Input
                        id="accounts-self-email"
                        v-model="profileForm.email"
                        type="email"
                        class="h-10 rounded-xl"
                        autocomplete="username"
                    />
                    <InputError :message="firstError('email')" />
                </div>

                <p
                    v-if="
                        currentUser.must_verify_email &&
                        !currentUser.email_verified_at
                    "
                    class="text-xs leading-5 text-muted-foreground"
                >
                    Dacă schimbi adresa, va trebui reverificată.
                </p>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        :disabled="isSubmitting"
                        @click="profileModalOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isSubmitting">
                        Save profile
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="passwordModalOpen"
        @update:open="passwordModalOpen = $event"
    >
        <DialogContent class="sm:max-w-xl">
            <DialogHeader class="space-y-2">
                <DialogTitle>Change password</DialogTitle>
                <DialogDescription>
                    Update the password for the account currently logged in.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitPassword">
                <div class="grid gap-2">
                    <Label for="accounts-current-password">
                        Current password
                    </Label>
                    <Input
                        id="accounts-current-password"
                        v-model="passwordForm.current_password"
                        type="password"
                        class="h-10 rounded-xl"
                        autocomplete="current-password"
                    />
                    <InputError :message="firstError('current_password')" />
                </div>

                <div class="grid gap-2">
                    <Label for="accounts-new-password">New password</Label>
                    <Input
                        id="accounts-new-password"
                        v-model="passwordForm.password"
                        type="password"
                        class="h-10 rounded-xl"
                        autocomplete="new-password"
                    />
                    <InputError :message="firstError('password')" />
                </div>

                <div class="grid gap-2">
                    <Label for="accounts-password-confirmation">
                        Confirm password
                    </Label>
                    <Input
                        id="accounts-password-confirmation"
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        class="h-10 rounded-xl"
                        autocomplete="new-password"
                    />
                    <InputError
                        :message="firstError('password_confirmation')"
                    />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        :disabled="isSubmitting"
                        @click="passwordModalOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isSubmitting">
                        Save password
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="securityModalOpen"
        @update:open="securityModalOpen = $event"
    >
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader class="space-y-2">
                <DialogTitle>Two-Factor Authentication</DialogTitle>
                <DialogDescription>
                    Manage 2FA from a compact modal instead of a separate page.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div v-if="!twoFactorEnabled" class="space-y-4">
                    <Badge
                        variant="secondary"
                        class="rounded-full px-3 py-1 text-xs"
                    >
                        Disabled
                    </Badge>

                    <p class="text-sm leading-6 text-muted-foreground">
                        Enable two-factor authentication to require a TOTP code
                        during sign-in for this administrator account.
                    </p>

                    <div>
                        <Button
                            v-if="hasSetupData"
                            class="rounded-xl"
                            @click="showSetupModal = true"
                        >
                            <ShieldCheck class="size-4" />
                            Continue setup
                        </Button>

                        <Form
                            v-else
                            v-bind="enable.form()"
                            @success="handleTwoFactorEnable"
                            #default="{ processing }"
                        >
                            <Button
                                type="submit"
                                class="rounded-xl"
                                :disabled="processing"
                            >
                                <ShieldCheck class="size-4" />
                                Enable 2FA
                            </Button>
                        </Form>
                    </div>
                </div>

                <div v-else class="space-y-4">
                    <Badge
                        variant="default"
                        class="rounded-full px-3 py-1 text-xs"
                    >
                        Enabled
                    </Badge>

                    <p class="text-sm leading-6 text-muted-foreground">
                        Recovery codes are available below in case you lose
                        access to your authenticator app.
                    </p>

                    <TwoFactorRecoveryCodes />

                    <Form
                        v-bind="disable.form()"
                        @success="handleTwoFactorDisabled"
                        #default="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="destructive"
                            class="rounded-xl"
                            :disabled="processing"
                        >
                            <ShieldBan class="size-4" />
                            Disable 2FA
                        </Button>
                    </Form>
                </div>
            </div>

            <TwoFactorSetupModal
                v-model:isOpen="showSetupModal"
                :requiresConfirmation="
                    currentUser.requires_two_factor_confirmation
                "
                :twoFactorEnabled="twoFactorEnabled"
                @confirmed="handleTwoFactorConfirmed"
            />
        </DialogContent>
    </Dialog>
</template>
