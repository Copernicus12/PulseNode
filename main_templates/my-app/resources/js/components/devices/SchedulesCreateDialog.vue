<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
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
import { Textarea } from '@/components/ui/textarea';

type ScheduleSocketOption = {
    value: string;
    label: string;
    title: string;
};

type DayOption = {
    value: string;
    label: string;
};

type TimePart = {
    hour: string;
    minute: string;
    period: 'AM' | 'PM';
};

type Props = {
    csrfToken: string;
    storeUrl: string;
    redirectRoute: string;
    redirectPage: number;
    shouldOpenOnMount?: boolean;
    validationErrors?: Record<string, string[]>;
    initialForm: {
        name: string;
        socket_index: string;
        action: string;
        is_active: boolean;
        start_time: string;
        end_time: string;
        days_of_week: string[];
        notes: string;
    };
    socketOptions: ScheduleSocketOption[];
    dayOptions: DayOption[];
};

const props = defineProps<Props>();

const open = ref(Boolean(props.shouldOpenOnMount));

const hourOptions = Array.from({ length: 12 }, (_, index) =>
    String(index + 1).padStart(2, '0'),
);
const minuteOptions = Array.from({ length: 60 }, (_, index) =>
    String(index).padStart(2, '0'),
);
const periodOptions: Array<'AM' | 'PM'> = ['AM', 'PM'];

function splitTime(value: string, fallback: TimePart): TimePart {
    if (!value) {
        return { ...fallback };
    }

    const [hourRaw = '', minuteRaw = '00'] = value.split(':');
    const hour24 = Number(hourRaw);

    if (Number.isNaN(hour24)) {
        return { ...fallback };
    }

    const hour12 = hour24 % 12 || 12;

    return {
        hour: String(hour12).padStart(2, '0'),
        minute: String(Number(minuteRaw || '0')).padStart(2, '0'),
        period: hour24 >= 12 ? 'PM' : 'AM',
    };
}

function toTwentyFourHour(value: TimePart): string {
    const hour12 = Number(value.hour || '0');
    const minute = String(value.minute || '00').padStart(2, '0');

    if (Number.isNaN(hour12)) {
        return '00:00';
    }

    let hour24 = hour12 % 12;

    if (value.period === 'PM') {
        hour24 += 12;
    }

    if (value.period === 'AM' && hour12 === 12) {
        hour24 = 0;
    }

    return `${String(hour24).padStart(2, '0')}:${minute}`;
}

const form = reactive({
    name: props.initialForm.name,
    socket_index: props.initialForm.socket_index,
    action: props.initialForm.action,
    is_active: props.initialForm.is_active,
    days_of_week: [...props.initialForm.days_of_week],
    notes: props.initialForm.notes,
});

const startTime = reactive<TimePart>(
    splitTime(props.initialForm.start_time, {
        hour: '08',
        minute: '00',
        period: 'AM',
    }),
);

const endTime = reactive<TimePart>(
    splitTime(props.initialForm.end_time, {
        hour: '09',
        minute: '00',
        period: 'AM',
    }),
);

function getSocketOption(socketIndex: string) {
    return (
        props.socketOptions.find((option) => option.value === socketIndex) ??
        props.socketOptions[0] ?? {
            value: '1',
            label: 'Socket 1',
            title: 'Add schedule for Socket 1',
        }
    );
}

const selectedSocket = computed(() => getSocketOption(form.socket_index));

const dialogTitle = computed(() => {
    if (selectedSocket.value?.label) {
        return `Create a schedule for ${selectedSocket.value.label}`;
    }

    return 'Create a new socket schedule';
});

const dialogDescription = computed(() => {
    if (selectedSocket.value?.title) {
        return selectedSocket.value.title;
    }

    return 'Pick a socket, set the time window, and save it as a weekly rule.';
});

function validationErrors(field: string) {
    return props.validationErrors?.[field] ?? [];
}

function resetForm(socketIndex?: string) {
    form.name = props.initialForm.name;
    form.socket_index = socketIndex || props.initialForm.socket_index || getSocketOption('1').value;
    form.action = props.initialForm.action;
    form.is_active = props.initialForm.is_active;
    form.days_of_week = [...props.initialForm.days_of_week];
    form.notes = props.initialForm.notes;

    const nextStart = splitTime(props.initialForm.start_time, {
        hour: '08',
        minute: '00',
        period: 'AM',
    });
    const nextEnd = splitTime(props.initialForm.end_time, {
        hour: '09',
        minute: '00',
        period: 'AM',
    });

    startTime.hour = nextStart.hour;
    startTime.minute = nextStart.minute;
    startTime.period = nextStart.period;

    endTime.hour = nextEnd.hour;
    endTime.minute = nextEnd.minute;
    endTime.period = nextEnd.period;
}

function openForSocket(socketIndex?: string) {
    resetForm(socketIndex);
    open.value = true;
}

function handleDocumentClick(event: MouseEvent) {
    const target = event.target as HTMLElement | null;
    const trigger = target?.closest('[data-modal-open="create-schedule-modal"]') as HTMLElement | null;

    if (!trigger) {
        return;
    }

    openForSocket(trigger.getAttribute('data-schedule-socket') || undefined);
}

function toggleDay(value: string, checked: boolean) {
    const existingIndex = form.days_of_week.indexOf(value);

    if (checked && existingIndex === -1) {
        form.days_of_week.push(value);
        return;
    }

    if (!checked && existingIndex !== -1) {
        form.days_of_week.splice(existingIndex, 1);
    }
}

const startTimeValue = computed(() => toTwentyFourHour(startTime));
const endTimeValue = computed(() => toTwentyFourHour(endTime));

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[85vh] overflow-hidden border-border/50 bg-card sm:max-w-3xl">
            <DialogHeader>
                <p class="text-[11px] uppercase tracking-[0.24em] text-muted-foreground">
                    Add schedule
                </p>
                <DialogTitle class="text-2xl tracking-tight">
                    {{ dialogTitle }}
                </DialogTitle>
                <DialogDescription>
                    {{ dialogDescription }}
                </DialogDescription>
            </DialogHeader>

            <form method="POST" :action="storeUrl" class="space-y-5">
                <input type="hidden" name="_token" :value="csrfToken" />
                <input type="hidden" name="redirect_route" :value="redirectRoute" />
                <input type="hidden" name="redirect_page" :value="redirectPage" />
                <input type="hidden" name="name" :value="form.name" />
                <input type="hidden" name="socket_index" :value="form.socket_index" />
                <input type="hidden" name="action" :value="form.action" />
                <input type="hidden" name="is_active" :value="form.is_active ? 1 : 0" />
                <input type="hidden" name="start_time" :value="startTimeValue" />
                <input type="hidden" name="end_time" :value="endTimeValue" />
                <input type="hidden" name="notes" :value="form.notes" />
                <template v-for="day in form.days_of_week" :key="day">
                    <input type="hidden" name="days_of_week[]" :value="day" />
                </template>

                <div class="max-h-[calc(85vh-11rem)] space-y-5 overflow-y-auto pr-1">
                    <FieldSet class="gap-4">
                        <FieldLegend class="text-base font-semibold">
                            Schedule details
                        </FieldLegend>
                        <FieldDescription>
                            Keep the structure simple, then use the rule per socket.
                        </FieldDescription>

                        <FieldGroup class="grid gap-5 md:grid-cols-2">
                            <Field>
                                <FieldLabel for="schedule-name" class="text-sm font-medium">
                                    Schedule name
                                </FieldLabel>
                                <Input
                                    id="schedule-name"
                                    v-model="form.name"
                                    data-modal-initial-focus
                                    type="text"
                                    placeholder="Morning focus"
                                    class="h-11 rounded-xl"
                                />
                                <FieldDescription>
                                    Give the schedule a short, readable name.
                                </FieldDescription>
                                <FieldError :errors="validationErrors('name')" />
                            </Field>

                            <Field>
                                <FieldLabel for="schedule-socket" class="text-sm font-medium">
                                    Socket
                                </FieldLabel>
                                <Select v-model="form.socket_index">
                                    <SelectTrigger id="schedule-socket" class="h-11 w-full rounded-xl">
                                        <SelectValue placeholder="Select socket" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="socket in socketOptions"
                                            :key="socket.value"
                                            :value="socket.value"
                                        >
                                            {{ socket.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <FieldDescription>
                                    Pick which relay should follow this rule.
                                </FieldDescription>
                                <FieldError :errors="validationErrors('socket_index')" />
                            </Field>
                        </FieldGroup>

                        <FieldGroup class="grid gap-5 md:grid-cols-2">
                            <Field>
                                <FieldLabel for="schedule-action" class="text-sm font-medium">
                                    Action
                                </FieldLabel>
                                <Select v-model="form.action">
                                    <SelectTrigger id="schedule-action" class="h-11 w-full rounded-xl">
                                        <SelectValue placeholder="Select action" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="on">
                                            Turn on
                                        </SelectItem>
                                        <SelectItem value="off">
                                            Turn off
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <FieldDescription>
                                    Decide what the socket should do when the window starts.
                                </FieldDescription>
                                <FieldError :errors="validationErrors('action')" />
                            </Field>

                            <Field>
                                <FieldLabel class="text-sm font-medium">
                                    Status
                                </FieldLabel>
                                <label class="mt-2 flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-border/40 bg-background/60 px-4 py-3 transition hover:bg-background/80">
                                    <Checkbox
                                        v-model="form.is_active"
                                        class="size-4 rounded-[6px] border-border data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground"
                                    />
                                    <div class="space-y-1">
                                        <p class="text-sm font-medium">
                                            Active schedule
                                        </p>
                                        <p class="text-sm text-muted-foreground">
                                            Enable it immediately after saving.
                                        </p>
                                    </div>
                                </label>
                                <FieldError :errors="validationErrors('is_active')" />
                            </Field>
                        </FieldGroup>
                    </FieldSet>

                    <FieldSeparator />

                    <FieldSet class="gap-4">
                        <FieldLegend class="text-base font-semibold">
                            Time window
                        </FieldLegend>
                        <FieldDescription>
                            Define when the socket should switch state.
                        </FieldDescription>

                        <FieldGroup class="grid gap-5 md:grid-cols-2">
                            <Field>
                                <FieldLabel class="text-sm font-medium">
                                    Start time
                                </FieldLabel>
                                <div class="overflow-hidden rounded-xl border border-border/40 bg-background/60">
                                    <div class="grid grid-cols-[1fr_1fr_auto] divide-x divide-border/40">
                                        <Select v-model="startTime.hour">
                                            <SelectTrigger class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0">
                                                <SelectValue placeholder="Hour" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="hour in hourOptions"
                                                    :key="hour"
                                                    :value="hour"
                                                >
                                                    {{ hour }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <Select v-model="startTime.minute">
                                            <SelectTrigger class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0">
                                                <SelectValue placeholder="Min" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="minute in minuteOptions"
                                                    :key="minute"
                                                    :value="minute"
                                                >
                                                    {{ minute }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <Select v-model="startTime.period">
                                            <SelectTrigger class="h-11 w-[92px] rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0">
                                                <SelectValue placeholder="AM" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="period in periodOptions"
                                                    :key="period"
                                                    :value="period"
                                                >
                                                    {{ period }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Will submit as {{ startTimeValue }}.
                                </p>
                                <FieldError :errors="validationErrors('start_time')" />
                            </Field>

                            <Field>
                                <FieldLabel class="text-sm font-medium">
                                    End time
                                </FieldLabel>
                                <div class="overflow-hidden rounded-xl border border-border/40 bg-background/60">
                                    <div class="grid grid-cols-[1fr_1fr_auto] divide-x divide-border/40">
                                        <Select v-model="endTime.hour">
                                            <SelectTrigger class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0">
                                                <SelectValue placeholder="Hour" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="hour in hourOptions"
                                                    :key="hour"
                                                    :value="hour"
                                                >
                                                    {{ hour }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <Select v-model="endTime.minute">
                                            <SelectTrigger class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0">
                                                <SelectValue placeholder="Min" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="minute in minuteOptions"
                                                    :key="minute"
                                                    :value="minute"
                                                >
                                                    {{ minute }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <Select v-model="endTime.period">
                                            <SelectTrigger class="h-11 w-[92px] rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0">
                                                <SelectValue placeholder="AM" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="period in periodOptions"
                                                    :key="period"
                                                    :value="period"
                                                >
                                                    {{ period }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Will submit as {{ endTimeValue }}.
                                </p>
                                <FieldError :errors="validationErrors('end_time')" />
                            </Field>
                        </FieldGroup>

                        <Field>
                            <FieldLabel class="text-sm font-medium">
                                Days of week
                            </FieldLabel>
                            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <label
                                    v-for="day in dayOptions"
                                    :key="day.value"
                                    class="flex cursor-pointer items-center gap-2 rounded-xl border border-border/40 bg-background/60 px-3 py-2 text-sm text-muted-foreground transition hover:bg-background/80"
                                    :class="form.days_of_week.includes(day.value) ? 'border-primary/40 bg-primary/5 text-foreground' : ''"
                                >
                                    <Checkbox
                                        :model-value="form.days_of_week.includes(day.value)"
                                        class="size-4 rounded-[6px] border-border data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground"
                                        @update:model-value="(checked) => toggleDay(day.value, checked === true)"
                                    />
                                    <span class="font-medium">{{ day.label }}</span>
                                </label>
                            </div>
                            <FieldDescription>
                                Choose one or more active days for this rule.
                            </FieldDescription>
                            <FieldError :errors="validationErrors('days_of_week')" />
                        </Field>

                        <Field>
                            <FieldLabel for="schedule-notes" class="text-sm font-medium">
                                Notes
                            </FieldLabel>
                            <Textarea
                                id="schedule-notes"
                                v-model="form.notes"
                                rows="3"
                                placeholder="Optional context for this rule"
                                class="rounded-xl"
                            />
                            <FieldDescription>
                                Add a quick reminder if you want to remember why the rule exists.
                            </FieldDescription>
                            <FieldError :errors="validationErrors('notes')" />
                        </Field>
                    </FieldSet>
                </div>

                <DialogFooter class="gap-2 sm:gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="outline" class="rounded-xl">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button type="submit" class="rounded-xl">
                        Save schedule
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
