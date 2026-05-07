<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import { Ban, Clock3, Mail, ShieldCheck, UserPlus } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import CurrentAccountSettingsPanel from '@/components/accounts/CurrentAccountSettingsPanel.vue';
import { useInitials } from '@/composables/useInitials';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Separator } from '@/components/ui/separator';
import { Toaster } from '@/components/ui/sonner';

type Summary = {
    total: number;
    admins: number;
    moderators: number;
    active_guests: number;
    blocked: number;
    pending_requests: number;
};

type Flash = {
    success?: string | null;
    error?: string | null;
    validation?: string | null;
};

type ManagedUser = {
    id: string;
    name: string;
    email: string;
    role: string;
    is_blocked: boolean;
    account_status: string;
    guest_expires_at?: string | null;
    blocked_at?: string | null;
    requested_at?: string | null;
    approved_at?: string | null;
    rejected_at?: string | null;
    created_at?: string | null;
    is_self: boolean;
    update_url: string;
    approve_url: string;
    reject_url: string;
    toggle_block_url: string;
    destroy_url: string;
};

type CurrentUser = {
    name: string;
    email: string;
    email_verified_at?: string | null;
    must_verify_email: boolean;
    two_factor_enabled: boolean;
    requires_two_factor_confirmation: boolean;
};

type AccountsPageProps = {
    summary: Summary;
    roles: string[];
    csrfToken: string;
    flash: Flash;
    validationErrors: Record<string, string[]>;
    currentUser: CurrentUser | null;
    users: ManagedUser[];
    routes: {
        store: string;
        profile_update: string;
        password_update: string;
    };
};

const props = defineProps<AccountsPageProps>();
const { getInitials } = useInitials();

const state = reactive<AccountsPageProps>({
    summary: { ...props.summary },
    roles: [...props.roles],
    csrfToken: props.csrfToken,
    flash: { ...props.flash },
    validationErrors: { ...props.validationErrors },
    currentUser: props.currentUser ? { ...props.currentUser } : null,
    users: props.users.map((user) => ({ ...user })),
    routes: { ...props.routes },
});

const createDialogOpen = ref(false);
const rejectDialogOpen = ref(false);
const rejectTargetUser = ref<ManagedUser | null>(null);
const selectedUserId = ref<string | null>(
    state.users.find((user) => !isPendingRequest(user))?.id ??
        state.users[0]?.id ??
        null,
);
const isSubmitting = ref(false);
const accountsNavViewport = ref<HTMLElement | null>(null);
const accountsScrollbarActive = ref(false);
const accountsScrollbar = reactive({
    visible: false,
    thumbHeight: '0px',
    thumbOffset: '0px',
});
let accountsScrollbarHideTimeout: ReturnType<typeof window.setTimeout> | null =
    null;

const newAccount = reactive({
    role: state.roles.includes('moderator')
        ? 'moderator'
        : (state.roles[0] ?? 'moderator'),
    guest_duration_hours: 12,
});

const accountForms = reactive(
    Object.fromEntries(
        state.users.map((user) => [
            user.id,
            {
                role: user.role,
                guest_duration_hours: defaultGuestHours(user),
            },
        ]),
    ) as Record<string, { role: string; guest_duration_hours: number }>,
);

const selectedUser = computed(
    () =>
        activeUsers.value.find((user) => user.id === selectedUserId.value) ??
        activeUsers.value[0] ??
        null,
);

const selectedForm = computed(() =>
    selectedUser.value ? accountForms[selectedUser.value.id] : null,
);

const pendingRequests = computed(() =>
    state.users.filter((user) => isPendingRequest(user)),
);

const activeUsers = computed(() =>
    state.users.filter((user) => !isPendingRequest(user)),
);

const compactStats = computed(() => [
    {
        label: 'Pending',
        value: state.summary.pending_requests,
        tone:
            state.summary.pending_requests > 0
                ? 'border-amber-500/20 bg-amber-500/10 text-amber-200'
                : 'border-border/40 bg-muted/30 text-muted-foreground',
    },
    {
        label: 'Blocked',
        value: state.summary.blocked,
        tone:
            state.summary.blocked > 0
                ? 'border-red-500/20 bg-red-500/10 text-red-300'
                : 'border-border/40 bg-muted/30 text-muted-foreground',
    },
    {
        label: 'Active guests',
        value: state.summary.active_guests,
        tone: 'border-border/40 bg-muted/30 text-muted-foreground',
    },
]);

const toasterOptions = computed(() => ({
    duration: 4200,
    closeButton: false,
    class: 'w-[22rem] rounded-2xl border border-border/50 bg-[rgba(18,18,18,0.96)] shadow-2xl backdrop-blur-md',
    descriptionClass: 'text-[11px] leading-4 text-muted-foreground',
    classes: {
        title: 'text-xs font-semibold',
        description: 'text-[11px] leading-4 text-muted-foreground',
        actionButton:
            '!h-7 !rounded-lg !border !px-2.5 !text-[11px] !font-medium',
        success:
            'border-emerald-500/45 bg-[rgba(9,34,24,0.97)] text-emerald-50',
        error: 'border-red-500/45 bg-[rgba(42,10,10,0.97)] text-red-50',
    },
}));

function showFlashToast(): void {
    if (state.flash.success) {
        toast.success(state.flash.success, {
            id: 'accounts-feedback',
            description: 'Accounts workspace updated successfully.',
            action: {
                label: 'Close',
                onClick: () => toast.dismiss('accounts-feedback'),
            },
        });
    }

    if (state.flash.error || state.flash.validation) {
        toast.error(
            state.flash.error ?? state.flash.validation ?? 'Action failed.',
            {
                id: 'accounts-feedback',
                description: 'Review the message and try again.',
                action: {
                    label: 'Close',
                    onClick: () => toast.dismiss('accounts-feedback'),
                },
            },
        );
    }
}

function defaultGuestHours(user: ManagedUser): number {
    if (user.role !== 'guest' || !user.guest_expires_at) return 12;

    const diffMs = Date.parse(user.guest_expires_at) - Date.now();
    if (!Number.isFinite(diffMs) || diffMs <= 0) return 1;

    return Math.max(1, Math.ceil(diffMs / (60 * 60 * 1000)));
}

function formatDate(value?: string | null): string {
    if (!value) return 'Not set';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'Invalid date';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function roleLabel(role: string): string {
    return role
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (value) => value.toUpperCase());
}

function isPendingRequest(user: ManagedUser): boolean {
    return user.account_status === 'pending';
}

function isRejectedRequest(user: ManagedUser): boolean {
    return user.account_status === 'rejected';
}

function isExpiredGuest(user: ManagedUser): boolean {
    if (user.role !== 'guest' || !user.guest_expires_at) return false;

    const expiresAt = Date.parse(user.guest_expires_at);

    return Number.isFinite(expiresAt) && expiresAt <= Date.now();
}

function guestState(user: ManagedUser): string {
    if (user.role !== 'guest') return 'Permanent access';
    if (!user.guest_expires_at) return 'Guest without expiry';
    if (user.is_blocked || isExpiredGuest(user)) return 'Expired or blocked';

    return `Until ${formatDate(user.guest_expires_at)}`;
}

function accountStatusLabel(user: ManagedUser): string {
    if (isPendingRequest(user)) return 'Pending review';
    if (isRejectedRequest(user)) return 'Rejected';
    if (user.is_blocked) return 'Blocked';
    if (user.role === 'guest') return 'Guest';

    return 'Active';
}

function accountStatusCopy(user: ManagedUser): string {
    if (isPendingRequest(user)) {
        return user.requested_at
            ? `Requested on ${formatDate(user.requested_at)}`
            : 'Waiting for admin approval.';
    }

    if (isRejectedRequest(user)) {
        return user.rejected_at
            ? `Rejected on ${formatDate(user.rejected_at)}`
            : 'This request was declined.';
    }

    if (user.is_blocked) {
        return user.blocked_at
            ? `Blocked on ${formatDate(user.blocked_at)}`
            : 'Access is currently disabled.';
    }

    if (user.role === 'guest') {
        return guestState(user);
    }

    return 'Persistent access enabled.';
}

function statusTone(user: ManagedUser): string {
    if (isPendingRequest(user))
        return 'border-amber-500/20 bg-amber-500/10 text-amber-200';
    if (isRejectedRequest(user))
        return 'border-red-500/20 bg-red-500/10 text-red-300';
    if (user.is_blocked) return 'border-red-500/20 bg-red-500/10 text-red-300';
    if (user.role === 'guest')
        return 'border-amber-500/20 bg-amber-500/10 text-amber-200';

    return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300';
}

function roleTone(role: string): string {
    if (role === 'admin') return 'border-primary/20 bg-primary/10 text-primary';
    if (role === 'guest')
        return 'border-amber-500/20 bg-amber-500/10 text-amber-200';

    return 'border-border/40 bg-muted/40 text-foreground';
}

function ensureAccountForms(users: ManagedUser[]): void {
    const activeIds = new Set(users.map((user) => user.id));

    Object.keys(accountForms).forEach((id) => {
        if (!activeIds.has(id)) {
            delete accountForms[id];
        }
    });

    users.forEach((user) => {
        accountForms[user.id] = {
            role: user.role,
            guest_duration_hours: defaultGuestHours(user),
        };
    });
}

function parsePayloadFromHtml(html: string): AccountsPageProps {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const payload = doc.getElementById('accounts-page-props');

    if (!payload?.textContent) {
        throw new Error('Unable to parse accounts payload.');
    }

    return JSON.parse(payload.textContent) as AccountsPageProps;
}

function syncState(next: AccountsPageProps): void {
    const previousSelectedId = selectedUserId.value;

    state.summary = { ...next.summary };
    state.roles = [...next.roles];
    state.csrfToken = next.csrfToken;
    state.flash = { ...next.flash };
    state.validationErrors = { ...next.validationErrors };
    state.currentUser = next.currentUser ? { ...next.currentUser } : null;
    state.users = next.users.map((user) => ({ ...user }));
    state.routes = { ...next.routes };

    ensureAccountForms(state.users);

    if (
        previousSelectedId &&
        state.users.some(
            (user) =>
                user.id === previousSelectedId &&
                !isPendingRequest(user),
        )
    ) {
        selectedUserId.value = previousSelectedId;
    } else {
        selectedUserId.value =
            state.users.find((user) => !isPendingRequest(user))?.id ??
            state.users[0]?.id ??
            null;
    }

    window.dispatchEvent(
        new CustomEvent('pulsenode:accounts-summary', {
        detail: {
            total: state.summary.total,
            active_guests: state.summary.active_guests,
            blocked: state.summary.blocked,
            pending_requests: state.summary.pending_requests,
        },
    }),
    );

    queueAccountsScrollbarSync();
}

function syncAccountsScrollbar(): void {
    const viewport = accountsNavViewport.value;

    if (!viewport) {
        accountsScrollbar.visible = false;
        accountsScrollbarActive.value = false;
        return;
    }

    const { clientHeight, scrollHeight, scrollTop } = viewport;
    const hasOverflow = scrollHeight - clientHeight > 1;

    accountsScrollbar.visible = hasOverflow;

    if (!hasOverflow) {
        accountsScrollbar.thumbHeight = '0px';
        accountsScrollbar.thumbOffset = '0px';
        accountsScrollbarActive.value = false;
        return;
    }

    const thumbHeight = Math.max(
        40,
        (clientHeight / scrollHeight) * clientHeight,
    );
    const maxScrollTop = Math.max(scrollHeight - clientHeight, 1);
    const maxThumbOffset = Math.max(clientHeight - thumbHeight, 0);
    const thumbOffset = (scrollTop / maxScrollTop) * maxThumbOffset;

    accountsScrollbar.thumbHeight = `${thumbHeight}px`;
    accountsScrollbar.thumbOffset = `${thumbOffset}px`;
}

function clearAccountsScrollbarHideTimeout(): void {
    if (accountsScrollbarHideTimeout === null) return;

    window.clearTimeout(accountsScrollbarHideTimeout);
    accountsScrollbarHideTimeout = null;
}

function scheduleAccountsScrollbarHide(delay = 520): void {
    if (typeof window === 'undefined') return;

    clearAccountsScrollbarHideTimeout();
    accountsScrollbarHideTimeout = window.setTimeout(() => {
        accountsScrollbarActive.value = false;
    }, delay);
}

function showAccountsScrollbar(): void {
    if (!accountsScrollbar.visible) return;

    accountsScrollbarActive.value = true;
    clearAccountsScrollbarHideTimeout();
}

function queueAccountsScrollbarSync(): void {
    if (typeof window === 'undefined') return;

    window.requestAnimationFrame(() => {
        syncAccountsScrollbar();
    });
}

function handleAccountsNavScroll(): void {
    syncAccountsScrollbar();
    showAccountsScrollbar();
    scheduleAccountsScrollbarHide(650);
}

function handleAccountsNavPointerEnter(): void {
    showAccountsScrollbar();
}

function handleAccountsNavPointerLeave(): void {
    scheduleAccountsScrollbarHide(180);
}

async function submitRequest(
    url: string,
    formData: FormData,
    options: { closeCreateDialog?: boolean } = {},
): Promise<boolean> {
    if (isSubmitting.value) return false;

    isSubmitting.value = true;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'text/html,application/xhtml+xml',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
            credentials: 'same-origin',
        });

        const html = await response.text();
        const payload = parsePayloadFromHtml(html);

        syncState(payload);

        const succeeded =
            !payload.flash.error &&
            !payload.flash.validation &&
            !Object.values(payload.validationErrors ?? {}).some(
                (messages) => messages.length > 0,
            );

        if (options.closeCreateDialog && succeeded) {
            createDialogOpen.value = false;
        }

        showFlashToast();
        return succeeded;
    } catch (error) {
        console.error('Accounts request failed', error);
        toast.error('Unable to update accounts right now.', {
            id: 'accounts-feedback',
            description: 'Please try again in a moment.',
        });
        return false;
    } finally {
        isSubmitting.value = false;
    }
}

async function submitCreateAccount(event: Event): Promise<void> {
    const form = event.currentTarget as HTMLFormElement | null;
    if (!form) return;

    await submitRequest(state.routes.store, new FormData(form), {
        closeCreateDialog: true,
    });
}

async function submitAccountUpdate(
    event: Event,
    user: ManagedUser,
): Promise<void> {
    const form = event.currentTarget as HTMLFormElement | null;
    if (!form) return;

    await submitRequest(user.update_url, new FormData(form));
}

async function submitToggleBlock(user: ManagedUser): Promise<void> {
    const formData = new FormData();
    formData.set('_token', state.csrfToken);

    await submitRequest(user.toggle_block_url, formData);
}

async function submitDestroy(user: ManagedUser): Promise<void> {
    const confirmed = window.confirm(
        'Delete this account? This action cannot be undone.',
    );

    if (!confirmed) return;

    const formData = new FormData();
    formData.set('_token', state.csrfToken);
    formData.set('_method', 'DELETE');

    await submitRequest(user.destroy_url, formData);
}

async function submitApproveRequest(user: ManagedUser): Promise<void> {
    const formData = new FormData();
    formData.set('_token', state.csrfToken);

    await submitRequest(user.approve_url, formData);
}

async function submitRejectRequest(user: ManagedUser): Promise<void> {
    rejectTargetUser.value = user;
    rejectDialogOpen.value = true;
}

async function confirmRejectRequest(): Promise<void> {
    if (!rejectTargetUser.value) return;

    const formData = new FormData();
    formData.set('_token', state.csrfToken);

    const target = rejectTargetUser.value;
    const succeeded = await submitRequest(target.reject_url, formData);

    if (succeeded) {
        rejectDialogOpen.value = false;
        rejectTargetUser.value = null;
    }
}

watch(rejectDialogOpen, (isOpen) => {
    if (!isOpen) {
        rejectTargetUser.value = null;
    }
});

showFlashToast();

onMounted(() => {
    queueAccountsScrollbarSync();
    window.addEventListener('resize', queueAccountsScrollbarSync);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', queueAccountsScrollbarSync);
    clearAccountsScrollbarHideTimeout();
});

watch(
    () => state.users.length,
    async () => {
        await nextTick();
        syncAccountsScrollbar();
    },
);
</script>

<template>
    <div class="mx-auto max-w-[1280px] space-y-4">
        <Toaster
            position="top-right"
            :expand="false"
            :visible-toasts="1"
            :toast-options="toasterOptions"
            container-aria-label="Accounts notifications"
        />

        <Dialog v-model:open="rejectDialogOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader class="space-y-2">
                    <DialogTitle>Reject account request?</DialogTitle>
                    <DialogDescription>
                        This will remove the pending request and the user will
                        need to submit a new request if they want access
                        again.
                    </DialogDescription>
                </DialogHeader>

                <div
                    class="rounded-2xl border border-border/30 bg-background/70 p-4 text-sm leading-6 text-muted-foreground"
                >
                    <p class="font-medium text-foreground">
                        {{ rejectTargetUser?.name || 'Selected request' }}
                    </p>
                    <p class="mt-1 break-all">
                        {{ rejectTargetUser?.email || 'No email available' }}
                    </p>
                    <p class="mt-3">
                        If you continue, the request will be rejected and the
                        pending account removed from the list.
                    </p>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" :disabled="isSubmitting">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="isSubmitting || rejectTargetUser === null"
                        @click="confirmRejectRequest"
                    >
                        Reject request
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Card id="accounts-workspace-header" class="relative overflow-hidden border-border/40 shadow-none">
            <div
                class="pointer-events-none absolute inset-0 bg-linear-to-r from-primary/8 via-transparent to-transparent"
            />

            <CardHeader class="relative gap-3 p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <CardTitle class="text-xl tracking-tight">
                            Accounts
                        </CardTitle>
                        <CardDescription class="mt-1 text-sm leading-5">
                            Compact account navigation with quick access
                            editing and request approvals.
                        </CardDescription>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Badge
                            v-for="item in compactStats"
                            :key="item.label"
                            variant="outline"
                            class="rounded-full px-3 py-1 text-xs"
                            :class="item.tone"
                        >
                            {{ item.label }}: {{ item.value }}
                        </Badge>

                        <Dialog v-model:open="createDialogOpen">
                            <DialogTrigger as-child>
                                <Button class="h-9 rounded-xl px-4">
                                    <UserPlus class="size-4" />
                                    Create account
                                </Button>
                            </DialogTrigger>

                            <DialogContent class="sm:max-w-xl">
                                <DialogHeader class="space-y-2">
                                    <DialogTitle>Create account</DialogTitle>
                                    <DialogDescription>
                                        Add a new admin, moderator, or guest
                                        without leaving the accounts workspace.
                                    </DialogDescription>
                                </DialogHeader>

                                <form
                                    :action="state.routes.store"
                                    method="POST"
                                    class="space-y-4"
                                    @submit.prevent="submitCreateAccount"
                                >
                                    <input
                                        type="hidden"
                                        name="_token"
                                        :value="state.csrfToken"
                                    />

                                    <div class="grid gap-4">
                                        <div class="space-y-2">
                                            <Label for="account-name">
                                                Full name
                                            </Label>
                                            <Input
                                                id="account-name"
                                                name="name"
                                                type="text"
                                                required
                                                class="h-10 rounded-xl"
                                            />
                                        </div>

                                        <div class="space-y-2">
                                            <Label for="account-email">
                                                Email address
                                            </Label>
                                            <Input
                                                id="account-email"
                                                name="email"
                                                type="email"
                                                required
                                                class="h-10 rounded-xl"
                                            />
                                        </div>

                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div class="space-y-2">
                                                <Label for="account-password">
                                                    Password
                                                </Label>
                                                <Input
                                                    id="account-password"
                                                    name="password"
                                                    type="password"
                                                    required
                                                    class="h-10 rounded-xl"
                                                />
                                            </div>
                                            <div class="space-y-2">
                                                <Label
                                                    for="account-password-confirmation"
                                                >
                                                    Confirm password
                                                </Label>
                                                <Input
                                                    id="account-password-confirmation"
                                                    name="password_confirmation"
                                                    type="password"
                                                    required
                                                    class="h-10 rounded-xl"
                                                />
                                            </div>
                                        </div>

                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div class="space-y-2">
                                                <Label for="account-role">
                                                    Role
                                                </Label>
                                                <NativeSelect
                                                    id="account-role"
                                                    v-model="newAccount.role"
                                                    name="role"
                                                    class="rounded-xl"
                                                >
                                                    <NativeSelectOption
                                                        v-for="role in state.roles"
                                                        :key="role"
                                                        :value="role"
                                                    >
                                                        {{ roleLabel(role) }}
                                                    </NativeSelectOption>
                                                </NativeSelect>
                                            </div>

                                            <div class="space-y-2">
                                                <Label
                                                    for="guest-duration-hours"
                                                >
                                                    Guest hours
                                                </Label>
                                                <Input
                                                    id="guest-duration-hours"
                                                    v-model="
                                                        newAccount.guest_duration_hours
                                                    "
                                                    name="guest_duration_hours"
                                                    type="number"
                                                    min="1"
                                                    max="720"
                                                    :disabled="
                                                        newAccount.role !==
                                                        'guest'
                                                    "
                                                    class="h-10 rounded-xl"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <DialogFooter class="gap-2">
                                        <DialogClose as-child>
                                            <Button
                                                variant="secondary"
                                                :disabled="isSubmitting"
                                            >
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            :disabled="isSubmitting"
                                        >
                                            Create account
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>
            </CardHeader>
        </Card>

        <Card
            id="accounts-pending-requests"
            class="border-border/40 shadow-none"
        >
            <CardHeader class="gap-3 p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <CardTitle class="text-lg tracking-tight">
                            Pending requests
                        </CardTitle>
                        <CardDescription class="mt-1 text-sm leading-5">
                            Review account requests before they appear in the
                            active accounts list.
                        </CardDescription>
                    </div>

                    <Badge
                        variant="outline"
                        class="rounded-full px-3 py-1 text-xs"
                        :class="
                            pendingRequests.length > 0
                                ? 'border-amber-500/20 bg-amber-500/10 text-amber-200'
                                : 'border-border/40 bg-muted/30 text-muted-foreground'
                        "
                    >
                        {{ pendingRequests.length }} waiting
                    </Badge>
                </div>
            </CardHeader>

            <CardContent class="p-4 pt-0 sm:p-5 sm:pt-0">
                <div v-if="pendingRequests.length > 0" class="overflow-x-auto rounded-2xl bg-secondary/40 ring-1 ring-border/30">
                    <table class="w-full min-w-[980px] text-sm">
                        <thead>
                            <tr class="border-b border-border/30 bg-secondary/40 text-left text-xs text-muted-foreground">
                                <th class="px-3 py-2.5 font-medium">Request</th>
                                <th class="px-3 py-2.5 font-medium">Email</th>
                                <th class="px-3 py-2.5 font-medium">Requested</th>
                                <th class="px-3 py-2.5 font-medium">Status</th>
                                <th class="px-3 py-2.5 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="user in pendingRequests"
                                :key="user.id"
                                class="border-b border-border/20 last:border-0"
                            >
                                <td class="px-3 py-2.5">
                                    <div class="font-medium text-foreground">
                                        {{ user.name }}
                                    </div>
                                    <div class="mt-1 text-xs text-muted-foreground">
                                        Role: {{ roleLabel(user.role) }}
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-muted-foreground">
                                    {{ user.email }}
                                </td>
                                <td class="px-3 py-2.5 text-muted-foreground">
                                    {{ user.requested_at ? formatDate(user.requested_at) : 'Just now' }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium"
                                        :class="statusTone(user)"
                                    >
                                        {{ accountStatusLabel(user) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            class="h-8 rounded-xl px-3"
                                            :disabled="isSubmitting"
                                            @click="submitApproveRequest(user)"
                                        >
                                            <ShieldCheck class="size-4" />
                                            Approve
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            class="h-8 rounded-xl px-3"
                                            :disabled="isSubmitting"
                                            @click="submitRejectRequest(user)"
                                        >
                                            Reject
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-else
                    class="rounded-2xl border border-dashed border-border/40 bg-background/40 p-5 text-sm leading-5 text-muted-foreground"
                >
                    No pending requests right now.
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-4 xl:grid-cols-[320px_minmax(0,1fr)]">
            <Card
                class="border-border/40 shadow-none xl:sticky xl:top-4 xl:self-start"
            >
                <CardHeader class="gap-2 p-4">
                    <CardTitle class="text-sm font-semibold">
                        Account navigation
                    </CardTitle>
                    <CardDescription class="text-sm leading-5">
                        Select an account from the scrollable list.
                    </CardDescription>
                </CardHeader>

                <CardContent class="p-4 pt-0">
                    <div
                        class="group relative"
                        @mouseenter="handleAccountsNavPointerEnter"
                        @mouseleave="handleAccountsNavPointerLeave"
                    >
                        <nav
                            ref="accountsNavViewport"
                            class="pulsenode-scrollbar h-[17rem] space-y-2 overflow-y-auto overscroll-contain pr-4 sm:h-[18.5rem] xl:h-[26rem]"
                            aria-label="Accounts"
                            @scroll="handleAccountsNavScroll"
                        >
                            <button
                                v-for="user in activeUsers"
                                :key="user.id"
                                type="button"
                                class="w-full rounded-2xl border p-3 text-left transition"
                                :class="
                                    selectedUser?.id === user.id
                                        ? 'border-primary/30 bg-primary/10'
                                        : 'border-border/30 bg-background/50 hover:bg-muted/40'
                                "
                                @click="selectedUserId = user.id"
                            >
                                <div class="flex items-start gap-3">
                                    <Avatar
                                        class="size-10 border border-border/30 bg-background"
                                    >
                                        <AvatarFallback
                                            class="bg-primary/10 text-xs font-semibold text-primary"
                                        >
                                            {{ getInitials(user.name) }}
                                        </AvatarFallback>
                                    </Avatar>

                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex items-center justify-between gap-2"
                                        >
                                            <p
                                                class="truncate text-sm font-semibold"
                                            >
                                                {{ user.name }}
                                            </p>
                                            <Badge
                                                variant="outline"
                                                class="rounded-full px-2 py-0.5 text-[10px]"
                                                :class="statusTone(user)"
                                            >
                                                {{ accountStatusLabel(user) }}
                                            </Badge>
                                        </div>

                                        <p
                                            class="mt-1 truncate text-xs text-muted-foreground"
                                        >
                                            {{ user.email }}
                                        </p>

                                        <div
                                            class="mt-2 flex flex-wrap items-center gap-2"
                                        >
                                            <Badge
                                                variant="outline"
                                                class="rounded-full px-2 py-0.5 text-[10px]"
                                                :class="roleTone(user.role)"
                                            >
                                                {{ roleLabel(user.role) }}
                                            </Badge>
                                            <Badge
                                                v-if="user.is_self"
                                                variant="secondary"
                                                class="rounded-full px-2 py-0.5 text-[10px]"
                                            >
                                                You
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                            </button>

                            <div
                                v-if="activeUsers.length === 0"
                                class="rounded-2xl border border-dashed border-border/40 bg-background/40 p-4 text-sm leading-5 text-muted-foreground"
                            >
                                No active accounts yet. Approve a pending
                                request to unlock the first account.
                            </div>
                        </nav>

                        <div
                            v-if="accountsScrollbar.visible"
                            class="pointer-events-none absolute inset-y-0 right-1 w-1 rounded-full bg-transparent transition-opacity duration-200"
                            :class="
                                accountsScrollbarActive
                                    ? 'opacity-100'
                                    : 'opacity-0'
                            "
                        >
                            <div
                                class="w-full rounded-full bg-muted-foreground/45 transition-transform duration-150"
                                :style="{
                                    height: accountsScrollbar.thumbHeight,
                                    transform: `translateY(${accountsScrollbar.thumbOffset})`,
                                }"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card id="accounts-workspace-detail" class="border-border/40 shadow-none">
                <template v-if="selectedUser && selectedForm">
                    <CardHeader class="gap-3 p-4 sm:p-5">
                        <div
                            class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div class="flex items-start gap-3">
                                <Avatar
                                    class="size-11 border border-border/30 bg-background"
                                >
                                    <AvatarFallback
                                        class="bg-primary/10 text-sm font-semibold text-primary"
                                    >
                                        {{ getInitials(selectedUser.name) }}
                                    </AvatarFallback>
                                </Avatar>

                                <div>
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <CardTitle
                                            class="text-lg tracking-tight"
                                        >
                                            {{ selectedUser.name }}
                                        </CardTitle>
                                        <Badge
                                            variant="outline"
                                            class="rounded-full px-2.5 py-0.5 text-[10px]"
                                            :class="roleTone(selectedUser.role)"
                                        >
                                            {{ roleLabel(selectedUser.role) }}
                                        </Badge>
                                        <Badge
                                            variant="outline"
                                            class="rounded-full px-2.5 py-0.5 text-[10px]"
                                            :class="statusTone(selectedUser)"
                                        >
                                            {{
                                                accountStatusLabel(selectedUser)
                                            }}
                                        </Badge>
                                    </div>

                                    <p
                                        class="mt-1 flex items-center gap-2 text-sm text-muted-foreground"
                                    >
                                        <Mail class="size-3.5" />
                                        <span class="break-all">{{
                                            selectedUser.email
                                        }}</span>
                                    </p>
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-border/30 bg-card/50 px-3 py-2 text-xs leading-5 text-muted-foreground"
                            >
                                {{ accountStatusCopy(selectedUser) }}
                            </div>
                        </div>

                        <div
                            v-if="isPendingRequest(selectedUser)"
                            class="rounded-2xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs leading-5 text-amber-200"
                        >
                            This request was created from the public request
                            form. Approve it to activate the account, or reject
                            it to remove the request.
                        </div>
                    </CardHeader>

                    <CardContent class="space-y-4 p-4 pt-0 sm:p-5 sm:pt-0">
                        <div class="grid gap-3 md:grid-cols-3">
                            <div
                                class="rounded-2xl border border-border/30 bg-card/50 p-3"
                            >
                                <p
                                    class="text-[10px] tracking-[0.16em] text-muted-foreground uppercase"
                                >
                                    Created
                                </p>
                                <p class="mt-1.5 text-sm leading-5 font-medium">
                                    {{ formatDate(selectedUser.created_at) }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-border/30 bg-card/50 p-3"
                            >
                                <p
                                    class="text-[10px] tracking-[0.16em] text-muted-foreground uppercase"
                                >
                                    Guest access
                                </p>
                                <p class="mt-1.5 text-sm leading-5 font-medium">
                                    {{ guestState(selectedUser) }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-border/30 bg-card/50 p-3"
                            >
                                <p
                                    class="text-[10px] tracking-[0.16em] text-muted-foreground uppercase"
                                >
                                    Blocked at
                                </p>
                                <p class="mt-1.5 text-sm leading-5 font-medium">
                                    {{ formatDate(selectedUser.blocked_at) }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="rounded-3xl border border-border/30 bg-card/60 p-4"
                        >
                            <div
                                v-if="isPendingRequest(selectedUser)"
                                class="mb-4 grid gap-3 sm:grid-cols-2"
                            >
                                <Button
                                    type="button"
                                    class="h-10 w-full rounded-xl"
                                    :disabled="isSubmitting"
                                    @click="submitApproveRequest(selectedUser)"
                                >
                                    <ShieldCheck class="size-4" />
                                    Approve request
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    class="h-10 w-full rounded-xl"
                                    :disabled="isSubmitting"
                                    @click="submitRejectRequest(selectedUser)"
                                >
                                    Reject request
                                </Button>
                            </div>

                            <form
                                :action="selectedUser.update_url"
                                method="POST"
                                class="space-y-4"
                                @submit.prevent="
                                    submitAccountUpdate($event, selectedUser)
                                "
                            >
                                <input
                                    type="hidden"
                                    name="_token"
                                    :value="state.csrfToken"
                                />
                                <input
                                    type="hidden"
                                    name="_method"
                                    value="PATCH"
                                />

                                <div
                                    class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_160px_auto]"
                                >
                                    <div class="space-y-2">
                                        <Label :for="`role-${selectedUser.id}`">
                                            Role
                                        </Label>
                                        <NativeSelect
                                            :id="`role-${selectedUser.id}`"
                                            v-model="selectedForm.role"
                                            name="role"
                                            class="rounded-xl"
                                            :disabled="isPendingRequest(selectedUser)"
                                        >
                                            <NativeSelectOption
                                                v-for="role in state.roles"
                                                :key="role"
                                                :value="role"
                                            >
                                                {{ roleLabel(role) }}
                                            </NativeSelectOption>
                                        </NativeSelect>
                                    </div>

                                    <div class="space-y-2">
                                        <Label
                                            :for="`guest-hours-${selectedUser.id}`"
                                        >
                                            Guest hours
                                        </Label>
                                        <Input
                                            :id="`guest-hours-${selectedUser.id}`"
                                            v-model="
                                                selectedForm.guest_duration_hours
                                            "
                                            name="guest_duration_hours"
                                            type="number"
                                            min="1"
                                            max="720"
                                            :disabled="
                                                selectedForm.role !== 'guest' ||
                                                isPendingRequest(selectedUser)
                                            "
                                            class="h-10 rounded-xl"
                                        />
                                    </div>

                                    <div class="flex items-end lg:justify-end">
                                        <Button
                                            type="submit"
                                            class="h-10 w-full rounded-xl lg:w-auto"
                                            :disabled="
                                                isSubmitting ||
                                                isPendingRequest(selectedUser)
                                            "
                                        >
                                            Save access
                                        </Button>
                                    </div>
                                </div>
                            </form>

                            <Separator class="my-4" />

                            <div class="grid gap-3 sm:grid-cols-2">
                                <form
                                    :action="selectedUser.toggle_block_url"
                                    method="POST"
                                    @submit.prevent="
                                        submitToggleBlock(selectedUser)
                                    "
                                >
                                    <input
                                        type="hidden"
                                        name="_token"
                                        :value="state.csrfToken"
                                    />
                                    <Button
                                        type="submit"
                                        class="h-10 w-full rounded-xl"
                                        variant="outline"
                                        :disabled="selectedUser.is_self || isSubmitting || isPendingRequest(selectedUser)"
                                    >
                                        <Ban class="size-4" />
                                        {{
                                            selectedUser.is_blocked
                                                ? 'Unblock account'
                                                : 'Block account'
                                        }}
                                    </Button>
                                </form>

                                <form
                                    :action="selectedUser.destroy_url"
                                    method="POST"
                                    @submit.prevent="
                                        submitDestroy(selectedUser)
                                    "
                                >
                                    <input
                                        type="hidden"
                                        name="_token"
                                        :value="state.csrfToken"
                                    />
                                    <input
                                        type="hidden"
                                        name="_method"
                                        value="DELETE"
                                    />
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        class="h-10 w-full rounded-xl"
                                        :disabled="
                                            selectedUser.is_self || isSubmitting
                                        "
                                    >
                                        Delete account
                                    </Button>
                                </form>
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div
                                class="rounded-2xl border border-border/30 bg-background/50 p-3 text-sm leading-5 text-muted-foreground"
                            >
                                <p
                                    class="flex items-center gap-2 font-medium text-foreground"
                                >
                                    <Clock3
                                        class="size-4 text-muted-foreground"
                                    />
                                    Guest expiry
                                </p>
                                <p class="mt-1.5">
                                    Guests should use short access windows so
                                    permissions close automatically.
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-border/30 bg-background/50 p-3 text-sm leading-5 text-muted-foreground"
                            >
                                <p
                                    class="flex items-center gap-2 font-medium text-foreground"
                                >
                                    <ShieldCheck
                                        class="size-4 text-muted-foreground"
                                    />
                                    Safety
                                </p>
                                <p class="mt-1.5">
                                    Your own admin account can be edited, but it
                                    cannot be blocked or deleted here.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </template>

                <template v-else>
                    <CardContent class="p-8 text-center">
                        <p class="text-sm font-medium">
                            No active accounts selected
                        </p>
                        <p class="mt-2 text-sm text-muted-foreground">
                            <template v-if="pendingRequests.length > 0">
                                Approve one of the pending requests above to
                                move it into the active accounts list.
                            </template>
                            <template v-else>
                                Open the create account dialog to add the first
                                account.
                            </template>
                        </p>
                    </CardContent>
                </template>
            </Card>
        </div>

        <CurrentAccountSettingsPanel
            v-if="state.currentUser"
            :current-user="state.currentUser"
            :validation-errors="state.validationErrors"
            :csrf-token="state.csrfToken"
            :is-submitting="isSubmitting"
            :profile-action="state.routes.profile_update"
            :password-action="state.routes.password_update"
            :submit-request="submitRequest"
        />
    </div>
</template>
