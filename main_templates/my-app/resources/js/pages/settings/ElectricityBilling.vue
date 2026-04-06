<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Check,
    CircleDollarSign,
    FolderKanban,
    Plus,
    ReceiptText,
    Sparkles,
    Trash2,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldSeparator,
    FieldSet,
    FieldLegend,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { toUrl } from '@/lib/utils';
import { update } from '@/routes/electricity-billing';

type CurrencyCode = 'RON' | 'EUR' | 'USD' | 'GBP' | 'CHF' | 'HUF';
type BillingProfile = {
    id: string;
    name: string;
    electricity_price_per_kwh: string;
    billing_currency: CurrencyCode;
    billing_tax_percent: string;
    created_at: string;
};

type Props = {
    billingSettings: {
        electricity_price_per_kwh: string;
        billing_currency: CurrencyCode;
        billing_tax_percent: string;
    };
    billingProfiles: BillingProfile[];
};

const props = defineProps<Props>();

const currencies: Array<{
    code: CurrencyCode;
    label: string;
    symbol: string;
    description: string;
}> = [
    {
        code: 'RON',
        label: 'Romanian Leu',
        symbol: 'lei',
        description: 'Useful for local billing in Romania.',
    },
    {
        code: 'EUR',
        label: 'Euro',
        symbol: 'EUR',
        description: 'Good for cross-border or EU estimates.',
    },
    {
        code: 'USD',
        label: 'US Dollar',
        symbol: 'USD',
        description: 'Useful for global comparisons.',
    },
    {
        code: 'GBP',
        label: 'British Pound',
        symbol: 'GBP',
        description: 'Handy for UK-style estimates.',
    },
    {
        code: 'CHF',
        label: 'Swiss Franc',
        symbol: 'CHF',
        description: 'Good for premium energy markets.',
    },
    {
        code: 'HUF',
        label: 'Hungarian Forint',
        symbol: 'HUF',
        description: 'Useful for regional conversions.',
    },
];

const form = useForm({
    electricity_price_per_kwh:
        props.billingSettings.electricity_price_per_kwh || '0.8',
    billing_currency: props.billingSettings.billing_currency || 'RON',
    billing_tax_percent: props.billingSettings.billing_tax_percent || '21',
});
const profileDialogOpen = ref(false);
const profileName = ref('');
const profileForm = useForm({
    name: '',
    electricity_price_per_kwh:
        props.billingSettings.electricity_price_per_kwh || '0.8',
    billing_currency: props.billingSettings.billing_currency || 'RON',
    billing_tax_percent: props.billingSettings.billing_tax_percent || '21',
});
const profiles = computed(() => props.billingProfiles ?? []);

const currentCurrency = computed(
    () =>
        currencies.find(
            (currency) => currency.code === form.billing_currency,
        ) ?? currencies[0],
);
const currentProfileMatch = computed(() =>
    profiles.value.find(
        (profile) =>
            profile.electricity_price_per_kwh ===
                form.electricity_price_per_kwh &&
            profile.billing_currency === form.billing_currency &&
            profile.billing_tax_percent === form.billing_tax_percent,
    ),
);

const pricePerKwh = computed(() => Number(form.electricity_price_per_kwh || 0));
const taxPercent = computed(() => Number(form.billing_tax_percent || 21));
const pricePerWh = computed(() => pricePerKwh.value / 1000);
const estimatedMonthlyConsumptionKwh = computed(() => 180);
const estimatedEnergyOnlyCost = computed(
    () => pricePerKwh.value * estimatedMonthlyConsumptionKwh.value,
);
const estimatedTaxAmount = computed(
    () => estimatedEnergyOnlyCost.value * (taxPercent.value / 100),
);
const estimatedMonthlyTotal = computed(
    () => estimatedEnergyOnlyCost.value + estimatedTaxAmount.value,
);

function formatCurrency(value: number) {
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: form.billing_currency,
            maximumFractionDigits: 2,
        }).format(value);
    } catch {
        return `${value.toFixed(2)} ${form.billing_currency}`;
    }
}

function submit(closeAfter = false) {
    form.transform((data) => ({
        ...data,
        billing_currency: data.billing_currency.toUpperCase(),
    })).patch(toUrl(update()), {
        preserveScroll: true,
        onSuccess: () => {
            if (closeAfter) {
                profileDialogOpen.value = false;
            }
        },
    });
}

function saveCurrentAsProfile() {
    const normalizedName = profileName.value.trim();
    if (!normalizedName) {
        return;
    }

    profileForm.name = normalizedName;
    profileForm.electricity_price_per_kwh = form.electricity_price_per_kwh;
    profileForm.billing_currency = form.billing_currency;
    profileForm.billing_tax_percent = form.billing_tax_percent;

    profileForm
        .transform((data) => ({
            ...data,
            billing_currency: data.billing_currency.toUpperCase(),
        }))
        .post('/settings/electricity-billing/profiles', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                profileName.value = '';
                profileForm.reset('name');
            },
        });
}

function applyProfile(profile: BillingProfile) {
    form.electricity_price_per_kwh = profile.electricity_price_per_kwh;
    form.billing_currency = profile.billing_currency;
    form.billing_tax_percent = profile.billing_tax_percent;
    submit(true);
}

function deleteProfile(id: string) {
    router.delete(`/settings/electricity-billing/profiles/${id}`, {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <Head title="Electricity bill settings" />

    <h1 class="sr-only">Electricity Bill Settings</h1>

    <SettingsLayout>
        <div class="space-y-6 pl-1 lg:pl-3">
            <header class="relative overflow-hidden rounded-[28px] border border-border/40 bg-gradient-to-r from-primary/12 via-primary/6 to-transparent px-6 py-5 shadow-none">
                <div class="absolute inset-y-0 left-0 w-24 bg-gradient-to-r from-primary/10 to-transparent blur-2xl" />
                <div class="relative space-y-2">
                    <h2 class="bg-gradient-to-r from-foreground via-foreground to-primary bg-clip-text text-xl font-semibold tracking-tight text-transparent">
                        Electricity bill settings
                    </h2>
                    <p class="max-w-3xl text-sm text-muted-foreground">
                        Configure the tariff used for cost estimates, current bill previews, and future energy spending insights.
                    </p>
                </div>
            </header>

            <div class="grid gap-4 md:grid-cols-3">
                <Card class="border-border/40 shadow-none">
                    <CardContent class="flex items-center gap-4 p-5">
                        <div class="rounded-2xl bg-primary/12 p-3 text-primary">
                            <ReceiptText class="h-5 w-5" />
                        </div>
                        <div class="space-y-1">
                            <p
                                class="text-xs tracking-[0.18em] text-muted-foreground uppercase"
                            >
                                Active tariff
                            </p>
                            <p class="text-xl font-semibold">
                                {{ formatCurrency(pricePerKwh) }}
                            </p>
                            <p class="text-xs text-muted-foreground">per kWh</p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-border/40 shadow-none">
                    <CardContent class="flex items-center gap-4 p-5">
                        <div class="rounded-2xl bg-primary/12 p-3 text-primary">
                            <CircleDollarSign class="h-5 w-5" />
                        </div>
                        <div class="space-y-1">
                            <p
                                class="text-xs tracking-[0.18em] text-muted-foreground uppercase"
                            >
                                Price per Wh
                            </p>
                            <p class="text-xl font-semibold">
                                {{ pricePerWh.toFixed(6) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                normalized for internal calculations
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-border/40 shadow-none">
                    <CardContent class="flex items-center gap-4 p-5">
                        <div class="rounded-2xl bg-primary/12 p-3 text-primary">
                            <Wallet class="h-5 w-5" />
                        </div>
                        <div class="space-y-1">
                            <p
                                class="text-xs tracking-[0.18em] text-muted-foreground uppercase"
                            >
                                Default tax
                            </p>
                            <p class="text-xl font-semibold">
                                {{ taxPercent.toFixed(2) }}%
                            </p>
                            <p class="text-xs text-muted-foreground">
                                implicit, but editable
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div
                class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(320px,0.9fr)]"
            >
                <Card class="self-start border-border/40 shadow-none">
                    <CardHeader class="gap-2 p-6">
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <div class="space-y-2">
                                <CardTitle class="text-xl"
                                    >Tariff configuration</CardTitle
                                >
                                <CardDescription class="text-sm leading-6">
                                    Enter the billing price as cost per kWh and
                                    choose the currency from the selector. The
                                    app will convert it internally for finer
                                    energy calculations.
                                </CardDescription>
                            </div>

                            <Badge
                                class="rounded-full px-3 py-1"
                                variant="secondary"
                            >
                                kWh-based input
                            </Badge>
                        </div>

                        <div
                            class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border/40 bg-background/60 px-4 py-3"
                        >
                            <div class="space-y-1">
                                <p class="text-sm font-medium">
                                    Billing profiles
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Save multiple tariff presets and apply them
                                    from a modal.
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <Badge variant="outline" class="rounded-full">
                                    {{ profiles.length }} profiles
                                </Badge>
                                <Badge
                                    v-if="currentProfileMatch"
                                    class="rounded-full"
                                >
                                    Active: {{ currentProfileMatch.name }}
                                </Badge>

                                <Dialog v-model:open="profileDialogOpen">
                                    <DialogTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="rounded-xl"
                                        >
                                            <FolderKanban class="h-4 w-4" />
                                            Manage profiles
                                        </Button>
                                    </DialogTrigger>

                                    <DialogContent
                                        class="max-h-[85vh] overflow-hidden sm:max-w-2xl"
                                    >
                                        <DialogHeader>
                                            <DialogTitle>
                                                Tariff profiles
                                            </DialogTitle>
                                            <DialogDescription>
                                                Save the current tariff as a
                                                reusable profile and apply any
                                                saved profile whenever you need
                                                it.
                                            </DialogDescription>
                                        </DialogHeader>

                                        <div
                                            class="max-h-[calc(85vh-9rem)] space-y-5 overflow-y-auto pr-2"
                                        >
                                            <div
                                                class="rounded-2xl border border-border/40 bg-background/60 p-4"
                                            >
                                                <FieldSet>
                                                    <FieldLegend
                                                        class="text-base font-semibold"
                                                    >
                                                        Billing inputs
                                                    </FieldLegend>
                                                    <FieldDescription>
                                                        Configure the active
                                                        tariff here, directly
                                                        inside the profile
                                                        modal.
                                                    </FieldDescription>

                                                    <FieldGroup class="mt-4">
                                                        <div
                                                            class="grid gap-5 md:grid-cols-2"
                                                        >
                                                            <Field>
                                                                <FieldLabel
                                                                    for="electricity_price_per_kwh"
                                                                    class="text-sm font-medium"
                                                                >
                                                                    Price per
                                                                    kWh
                                                                </FieldLabel>
                                                                <Input
                                                                    id="electricity_price_per_kwh"
                                                                    v-model="
                                                                        form.electricity_price_per_kwh
                                                                    "
                                                                    type="number"
                                                                    step="0.0001"
                                                                    min="0"
                                                                    class="h-11 rounded-xl"
                                                                    placeholder="0.8000"
                                                                />
                                                                <FieldDescription>
                                                                    Example:
                                                                    `0.8000`
                                                                    {{
                                                                        form.billing_currency
                                                                    }}
                                                                    per kWh.
                                                                </FieldDescription>
                                                                <FieldError
                                                                    :errors="[
                                                                        form
                                                                            .errors
                                                                            .electricity_price_per_kwh,
                                                                    ]"
                                                                />
                                                            </Field>

                                                            <Field>
                                                                <FieldLabel
                                                                    for="billing-currency"
                                                                    class="text-sm font-medium"
                                                                >
                                                                    Currency
                                                                </FieldLabel>
                                                                <Select
                                                                    v-model="
                                                                        form.billing_currency
                                                                    "
                                                                >
                                                                    <SelectTrigger
                                                                        id="billing-currency"
                                                                        class="h-11 w-full rounded-xl"
                                                                    >
                                                                        <SelectValue
                                                                            placeholder="Select currency"
                                                                        />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        <SelectItem
                                                                            v-for="currency in currencies"
                                                                            :key="
                                                                                currency.code
                                                                            "
                                                                            :value="
                                                                                currency.code
                                                                            "
                                                                        >
                                                                            {{
                                                                                currency.code
                                                                            }}
                                                                            ·
                                                                            {{
                                                                                currency.label
                                                                            }}
                                                                        </SelectItem>
                                                                    </SelectContent>
                                                                </Select>
                                                                <FieldDescription>
                                                                    Select the
                                                                    currency for
                                                                    bill
                                                                    previews.
                                                                </FieldDescription>
                                                                <FieldError
                                                                    :errors="[
                                                                        form
                                                                            .errors
                                                                            .billing_currency,
                                                                    ]"
                                                                />
                                                            </Field>
                                                        </div>

                                                        <FieldSeparator />

                                                        <div
                                                            class="grid gap-5 md:grid-cols-2"
                                                        >
                                                            <Field>
                                                                <FieldLabel
                                                                    for="billing_tax_percent"
                                                                    class="text-sm font-medium"
                                                                >
                                                                    Tax rate
                                                                </FieldLabel>
                                                                <Input
                                                                    id="billing_tax_percent"
                                                                    v-model="
                                                                        form.billing_tax_percent
                                                                    "
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0"
                                                                    max="100"
                                                                    class="h-11 rounded-xl"
                                                                    placeholder="21"
                                                                />
                                                                <FieldDescription>
                                                                    Percentage
                                                                    applied over
                                                                    the
                                                                    subtotal.
                                                                    Default is
                                                                    `21%`.
                                                                </FieldDescription>
                                                                <FieldError
                                                                    :errors="[
                                                                        form
                                                                            .errors
                                                                            .billing_tax_percent,
                                                                    ]"
                                                                />
                                                            </Field>

                                                            <Field>
                                                                <FieldLabel
                                                                    class="text-sm font-medium"
                                                                >
                                                                    Current
                                                                    billing
                                                                    summary
                                                                </FieldLabel>
                                                                <div
                                                                    class="rounded-2xl border border-border/40 bg-card p-4"
                                                                >
                                                                    <p
                                                                        class="text-sm font-semibold"
                                                                    >
                                                                        {{
                                                                            currentCurrency.code
                                                                        }}
                                                                        ·
                                                                        {{
                                                                            currentCurrency.label
                                                                        }}
                                                                    </p>
                                                                    <p
                                                                        class="mt-1 text-sm text-muted-foreground"
                                                                    >
                                                                        {{
                                                                            currentCurrency.description
                                                                        }}
                                                                    </p>
                                                                    <p
                                                                        class="mt-3 text-sm text-muted-foreground"
                                                                    >
                                                                        {{
                                                                            form.electricity_price_per_kwh
                                                                        }}
                                                                        {{
                                                                            form.billing_currency
                                                                        }}
                                                                        /kWh ·
                                                                        Tax
                                                                        {{
                                                                            taxPercent.toFixed(
                                                                                2,
                                                                            )
                                                                        }}%
                                                                    </p>
                                                                </div>
                                                            </Field>
                                                        </div>
                                                    </FieldGroup>
                                                </FieldSet>
                                            </div>

                                            <div
                                                class="rounded-2xl border border-border/40 bg-background/60 p-4"
                                            >
                                                <div
                                                    class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto]"
                                                >
                                                    <div class="space-y-2">
                                                        <FieldLabel
                                                            for="profile-name"
                                                            class="text-sm font-medium"
                                                        >
                                                            Profile name
                                                        </FieldLabel>
                                                        <Input
                                                            id="profile-name"
                                                            v-model="
                                                                profileName
                                                            "
                                                            class="h-11 rounded-xl"
                                                            placeholder="Example: Day tariff"
                                                        />
                                                    </div>

                                                    <div class="flex items-end">
                                                        <Button
                                                            type="button"
                                                            class="rounded-xl"
                                                            @click="
                                                                saveCurrentAsProfile
                                                            "
                                                        >
                                                            <Plus
                                                                class="h-4 w-4"
                                                            />
                                                            Save current
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div
                                                class="max-h-[24rem] space-y-3 overflow-y-auto pr-1"
                                            >
                                                <div
                                                    v-if="!profiles.length"
                                                    class="rounded-2xl border border-dashed border-border/50 bg-background/40 p-6 text-sm text-muted-foreground"
                                                >
                                                    No saved profiles yet. Set
                                                    the tariff you want, give it
                                                    a name, then save it here.
                                                </div>

                                                <div
                                                    v-for="profile in profiles"
                                                    :key="profile.id"
                                                    class="rounded-2xl border border-border/40 bg-background/60 p-4"
                                                >
                                                    <div
                                                        class="flex flex-wrap items-start justify-between gap-3"
                                                    >
                                                        <div class="space-y-2">
                                                            <div
                                                                class="flex flex-wrap items-center gap-2"
                                                            >
                                                                <p
                                                                    class="text-sm font-semibold"
                                                                >
                                                                    {{
                                                                        profile.name
                                                                    }}
                                                                </p>
                                                                <Badge
                                                                    v-if="
                                                                        currentProfileMatch?.id ===
                                                                        profile.id
                                                                    "
                                                                    class="rounded-full"
                                                                >
                                                                    <Check
                                                                        class="mr-1 h-3.5 w-3.5"
                                                                    />
                                                                    Active
                                                                </Badge>
                                                            </div>

                                                            <p
                                                                class="text-sm text-muted-foreground"
                                                            >
                                                                {{
                                                                    profile.electricity_price_per_kwh
                                                                }}
                                                                {{
                                                                    profile.billing_currency
                                                                }}
                                                                /kWh · Tax
                                                                {{
                                                                    profile.billing_tax_percent
                                                                }}%
                                                            </p>
                                                        </div>

                                                        <div
                                                            class="flex items-center gap-2"
                                                        >
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                class="rounded-xl"
                                                                @click="
                                                                    applyProfile(
                                                                        profile,
                                                                    )
                                                                "
                                                            >
                                                                Apply
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                class="rounded-xl text-destructive hover:text-destructive"
                                                                @click="
                                                                    deleteProfile(
                                                                        profile.id,
                                                                    )
                                                                "
                                                            >
                                                                <Trash2
                                                                    class="h-4 w-4"
                                                                />
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <DialogFooter>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                class="rounded-xl"
                                                @click="
                                                    profileDialogOpen = false
                                                "
                                            >
                                                Close
                                            </Button>
                                            <Button
                                                type="button"
                                                class="rounded-xl"
                                                :disabled="form.processing"
                                                @click="submit(true)"
                                            >
                                                Save billing settings
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent class="space-y-6 p-6 pt-0">
                        <Alert class="border-primary/20 bg-primary/5">
                            <Sparkles class="h-4 w-4" />
                            <AlertTitle>Main setting</AlertTitle>
                            <AlertDescription>
                                The most important field is
                                <strong>price per kWh</strong>. This is the
                                value used to estimate the electricity bill from
                                your measured consumption.
                            </AlertDescription>
                        </Alert>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div
                                class="rounded-2xl border border-border/40 bg-background/60 p-4"
                            >
                                <p
                                    class="text-xs tracking-[0.16em] text-muted-foreground uppercase"
                                >
                                    Current setup
                                </p>
                                <p class="mt-3 text-lg font-semibold">
                                    {{ form.electricity_price_per_kwh }}
                                    {{ form.billing_currency }}/kWh
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Tax {{ taxPercent.toFixed(2) }}% with
                                    internal conversion to Wh.
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-border/40 bg-background/60 p-4"
                            >
                                <p
                                    class="text-xs tracking-[0.16em] text-muted-foreground uppercase"
                                >
                                    Profile status
                                </p>
                                <p class="mt-3 text-lg font-semibold">
                                    {{
                                        currentProfileMatch?.name ??
                                        'Custom unsaved profile'
                                    }}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Open the modal to edit billing inputs, save
                                    presets, or apply an existing profile.
                                </p>
                            </div>
                        </div>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="form.recentlySuccessful"
                                class="text-sm font-medium text-primary"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </CardContent>
                </Card>

                <div class="space-y-6 self-start">
                    <Card class="border-border/40 shadow-none">
                        <CardHeader class="gap-2 p-6">
                            <CardTitle class="text-lg"
                                >Live estimate preview</CardTitle
                            >
                            <CardDescription>
                                Quick numbers based on the values currently
                                typed in the form.
                            </CardDescription>
                        </CardHeader>

                        <CardContent class="space-y-4 p-6 pt-0">
                            <div
                                class="rounded-2xl border border-border/40 bg-background/60 p-4"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span class="text-sm text-muted-foreground"
                                        >Energy only</span
                                    >
                                    <span class="text-sm font-semibold">
                                        {{
                                            formatCurrency(
                                                estimatedEnergyOnlyCost,
                                            )
                                        }}
                                    </span>
                                </div>
                                <Separator class="my-3" />
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span class="text-sm text-muted-foreground"
                                        >Tax ({{
                                            taxPercent.toFixed(2)
                                        }}%)</span
                                    >
                                    <span class="text-sm font-semibold">
                                        {{ formatCurrency(estimatedTaxAmount) }}
                                    </span>
                                </div>
                                <Separator class="my-3" />
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span class="text-sm text-muted-foreground"
                                        >Total example</span
                                    >
                                    <span
                                        class="text-base font-semibold text-primary"
                                    >
                                        {{
                                            formatCurrency(
                                                estimatedMonthlyTotal,
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div
                                    class="rounded-2xl border border-border/40 bg-background/60 p-4"
                                >
                                    <p
                                        class="text-xs tracking-[0.16em] text-muted-foreground uppercase"
                                    >
                                        50 kWh
                                    </p>
                                    <p class="mt-2 text-lg font-semibold">
                                        {{ formatCurrency(pricePerKwh * 50) }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-2xl border border-border/40 bg-background/60 p-4"
                                >
                                    <p
                                        class="text-xs tracking-[0.16em] text-muted-foreground uppercase"
                                    >
                                        300 kWh
                                    </p>
                                    <p class="mt-2 text-lg font-semibold">
                                        {{ formatCurrency(pricePerKwh * 300) }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border-border/40 shadow-none">
                        <CardHeader class="gap-2 p-6">
                            <CardTitle class="text-lg"
                                >Available currencies</CardTitle
                            >
                            <CardDescription>
                                Choose the one that best matches your provider
                                invoice.
                            </CardDescription>
                        </CardHeader>

                        <CardContent class="p-6 pt-0">
                            <div
                                class="max-h-[22rem] space-y-3 overflow-y-auto pr-1"
                            >
                                <div
                                    v-for="currency in currencies"
                                    :key="currency.code"
                                    class="flex items-start justify-between gap-3 rounded-2xl border border-border/40 bg-background/60 p-4"
                                >
                                    <div>
                                        <p class="text-sm font-semibold">
                                            {{ currency.code }} ·
                                            {{ currency.label }}
                                        </p>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{ currency.description }}
                                        </p>
                                    </div>

                                    <Badge
                                        :variant="
                                            currency.code ===
                                            form.billing_currency
                                                ? 'default'
                                                : 'outline'
                                        "
                                        class="rounded-full px-2.5 py-1"
                                    >
                                        {{ currency.symbol }}
                                    </Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                </div>
            </div>
        </div>
    </SettingsLayout>
</template>
