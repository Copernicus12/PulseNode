<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRightLeft,
    ArrowUpDown,
    ArrowUpRight,
    CalendarRange,
    ChevronRight,
    Download,
    Eye,
    FileText,
    Files,
    FolderCode,
    FolderOpen,
    FolderPlus,
    HardDriveUpload,
    Info,
    LayoutGrid,
    Pencil,
    ReceiptText,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuLabel,
    ContextMenuSeparator,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
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
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import SettingsLayout from '@/layouts/settings/Layout.vue';

type ArchiveItem = {
    id: string;
    name: string;
    period: string;
    year: number;
    month: number;
    size_bytes: number;
    mime_type: string;
    extension: string;
    uploaded_at?: string | null;
    preview_url: string;
    download_url: string;
    delete_url: string;
};

type Props = {
    invoiceArchive: {
        items: ArchiveItem[];
        folders: ArchiveFolderRecord[];
        current_period: string;
        accepted_types: string[];
    };
};

type ArchiveFolderRecord = {
    id: string;
    type: FolderType;
    key: string;
    year: number;
    month: number | null;
};

type MonthFolder = {
    key: string;
    year: string;
    label: string;
    monthNumber: number;
    fileCount: number;
    totalBytes: number;
    items: ArchiveItem[];
};

type YearFolder = {
    key: string;
    fileCount: number;
    totalBytes: number;
    months: MonthFolder[];
};

type FolderType = 'year' | 'period';
type FolderAction = 'rename' | 'move' | 'delete';
type FolderViewMode = 'grid' | 'list';
type ExplorerFlyoutKind = 'view' | 'sort';
type FolderSortMode =
    | 'newest'
    | 'oldest'
    | 'name-asc'
    | 'name-desc'
    | 'size-desc';
type FolderActionTarget = {
    type: FolderType;
    key: string;
    label: string;
    fileCount: number;
    totalBytes: number;
    monthCount: number | null;
    parentYear: string | null;
};

const props = defineProps<Props>();
const uploadDialogOpen = ref(false);
const folderActionDialogOpen = ref(false);
const createFolderDialogOpen = ref(false);
const folderInfoDialogOpen = ref(false);
const previewDialogOpen = ref(false);
const selectedYear = ref<string | null>(null);
const selectedMonth = ref<string | null>(null);
const uploadInput = ref<HTMLInputElement | null>(null);
const activeFolderAction = ref<FolderAction>('rename');
const activeFolderTarget = ref<FolderActionTarget | null>(null);
const folderInfoTarget = ref<FolderActionTarget | null>(null);
const previewItem = ref<ArchiveItem | null>(null);
const folderViewMode = ref<FolderViewMode>('grid');
const folderSortMode = ref<FolderSortMode>('newest');
const explorerFlyout = ref<{
    kind: ExplorerFlyoutKind;
    side: 'left' | 'right';
    top: number;
} | null>(null);
const uploadForm = useForm({
    billing_period: props.invoiceArchive.current_period,
    files: [] as File[],
});
const createFolderForm = useForm({
    folder_type: 'year' as FolderType,
    folder_key: '',
});
const folderActionForm = useForm({
    folder_type: 'year' as FolderType,
    folder_key: '',
    target_year: '',
    target_period: '',
});

const archiveItems = computed(() => props.invoiceArchive.items ?? []);
const archiveFolderRecords = computed(() => props.invoiceArchive.folders ?? []);
const acceptedTypesLabel = computed(() =>
    props.invoiceArchive.accepted_types.join(', '),
);
const contextMenuContentClass =
    'w-64 rounded-[22px] border border-border/60 bg-background/95 p-2 shadow-[0_24px_80px_-36px_rgba(0,0,0,0.92)] backdrop-blur-xl';
const contextMenuLabelClass =
    'px-3 pb-2 pt-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-muted-foreground';
const contextMenuItemClass =
    'rounded-xl px-3 py-2.5 text-[15px] font-medium [&_svg]:size-[1.05rem]';
const contextMenuSeparatorClass = 'mx-1 my-2 bg-border/60';
const explorerFlyoutWidth = 248;
const folderSortOptions: Array<{ value: FolderSortMode; label: string }> = [
    { value: 'newest', label: 'Newest first' },
    { value: 'oldest', label: 'Oldest first' },
    { value: 'name-asc', label: 'Name A-Z' },
    { value: 'name-desc', label: 'Name Z-A' },
    { value: 'size-desc', label: 'Largest first' },
];

const yearFolders = computed<YearFolder[]>(() => {
    const yearMap = new Map<
        string,
        {
            fileCount: number;
            totalBytes: number;
            months: Map<string, MonthFolder>;
        }
    >();

    const ensureYear = (yearKey: string) => {
        const existing = yearMap.get(yearKey);

        if (existing) {
            return existing;
        }

        const created = {
            fileCount: 0,
            totalBytes: 0,
            months: new Map<string, MonthFolder>(),
        };

        yearMap.set(yearKey, created);

        return created;
    };

    const ensureMonth = (
        yearKey: string,
        periodKey: string,
        monthNumber: number,
    ) => {
        const year = ensureYear(yearKey);
        const existing = year.months.get(periodKey);

        if (existing) {
            return existing;
        }

        const created: MonthFolder = {
            key: periodKey,
            year: yearKey,
            label: formatMonthLabel(periodKey),
            monthNumber,
            fileCount: 0,
            totalBytes: 0,
            items: [],
        };

        year.months.set(periodKey, created);

        return created;
    };

    archiveItems.value.forEach((item) => {
        const yearKey = String(item.year);
        const year = ensureYear(yearKey);
        const month = ensureMonth(yearKey, item.period, item.month);

        year.fileCount += 1;
        year.totalBytes += item.size_bytes;
        month.fileCount += 1;
        month.totalBytes += item.size_bytes;
        month.items.push(item);
    });

    archiveFolderRecords.value.forEach((folder) => {
        const yearKey = String(folder.year);

        ensureYear(yearKey);

        if (folder.type === 'period') {
            ensureMonth(yearKey, folder.key, folder.month ?? 0);
        }
    });

    return Array.from(yearMap.entries())
        .map(([yearKey, year]) => ({
            key: yearKey,
            fileCount: year.fileCount,
            totalBytes: year.totalBytes,
            months: sortMonthFolders(
                Array.from(year.months.values()).map((month) => ({
                    ...month,
                    items: sortArchiveItems(month.items),
                })),
            ),
        }))
        .sort(sortYearFolders);
});

const hasArchiveFolders = computed(() => yearFolders.value.length > 0);
const totalMonthFolders = computed(() =>
    yearFolders.value.reduce((total, year) => total + year.months.length, 0),
);
const activeYearFolder = computed(
    () =>
        yearFolders.value.find((folder) => folder.key === selectedYear.value) ??
        null,
);
const activeMonthFolder = computed(
    () =>
        activeYearFolder.value?.months.find(
            (folder) => folder.key === selectedMonth.value,
        ) ?? null,
);
const explorerPanelTitle = computed(() => {
    if (!selectedYear.value) return 'Year folders';
    if (!selectedMonth.value) return `${selectedYear.value} monthly folders`;

    return activeMonthFolder.value?.label ?? 'Invoices';
});
const explorerPanelDescription = computed(() => {
    if (!selectedYear.value) {
        return `${yearFolders.value.length} year folders and ${totalMonthFolders.value} month folders ready to browse.`;
    }

    if (!selectedMonth.value) {
        return `${activeYearFolder.value?.months.length ?? 0} month folders grouped under ${selectedYear.value}.`;
    }

    return `${activeMonthFolder.value?.fileCount ?? 0} files inside this billing folder.`;
});
const folderActionTitle = computed(() => {
    if (!activeFolderTarget.value) return 'Manage folder';

    const folderLabel =
        activeFolderTarget.value.type === 'year'
            ? 'year folder'
            : 'month folder';

    if (activeFolderAction.value === 'rename') {
        return `Rename ${folderLabel}`;
    }

    if (activeFolderAction.value === 'move') {
        return `Move ${folderLabel}`;
    }

    return `Delete ${folderLabel}`;
});
const folderActionDescription = computed(() => {
    if (!activeFolderTarget.value) return '';

    const folder = activeFolderTarget.value;
    const itemLabel = `${folder.fileCount} ${
        folder.fileCount === 1 ? 'file' : 'files'
    }`;

    if (activeFolderAction.value === 'rename') {
        return folder.type === 'year'
            ? `Change the year label for this folder. All ${itemLabel} will keep their original month.`
            : `Change the billing month label for this folder. All ${itemLabel} will stay grouped together.`;
    }

    if (activeFolderAction.value === 'move') {
        return folder.type === 'year'
            ? `Move all ${itemLabel} into another year while keeping each invoice in its current month.`
            : `Move all ${itemLabel} into another billing month folder.`;
    }

    return `Delete ${itemLabel} from "${folder.label}". This action cannot be undone.`;
});
const folderActionSubmitLabel = computed(() => {
    if (activeFolderAction.value === 'rename') return 'Rename folder';
    if (activeFolderAction.value === 'move') return 'Move folder';

    return 'Delete folder';
});
const folderActionSubmitDisabled = computed(() => {
    if (!activeFolderTarget.value || folderActionForm.processing) return true;
    if (activeFolderAction.value === 'delete') return false;

    if (activeFolderTarget.value.type === 'year') {
        const targetYear = folderActionForm.target_year.trim();

        return targetYear === '' || targetYear === activeFolderTarget.value.key;
    }

    const targetPeriod = folderActionForm.target_period.trim();

    return targetPeriod === '' || targetPeriod === activeFolderTarget.value.key;
});
const createFolderTypeLabel = computed(() =>
    createFolderForm.folder_type === 'year' ? 'year folder' : 'month folder',
);
const createFolderActionLabel = computed(() =>
    selectedYear.value || selectedMonth.value
        ? 'New month folder'
        : 'New year folder',
);
const createFolderDialogTitle = computed(() =>
    createFolderForm.folder_type === 'year'
        ? 'Create year folder'
        : 'Create month folder',
);
const createFolderDialogDescription = computed(() =>
    createFolderForm.folder_type === 'year'
        ? 'Add an empty year folder to the archive so you can organize invoices later.'
        : 'Add an empty billing month folder inside the current archive structure.',
);
const folderViewModeLabel = computed(() =>
    folderViewMode.value === 'grid' ? 'Grid' : 'List',
);
const folderSortLabel = computed(
    () =>
        folderSortOptions.find(
            (option) => option.value === folderSortMode.value,
        )?.label ?? 'Newest first',
);
const folderInfoDialogTitle = computed(() => {
    if (!folderInfoTarget.value) return 'Folder details';

    return `Get info for ${folderInfoTarget.value.label}`;
});
const folderInfoPath = computed(() => {
    if (!folderInfoTarget.value) return 'Invoice archive';

    if (folderInfoTarget.value.type === 'year') {
        return `Invoice archive / ${folderInfoTarget.value.key}`;
    }

    return `Invoice archive / ${folderInfoTarget.value.parentYear ?? folderInfoTarget.value.key.slice(0, 4)} / ${folderInfoTarget.value.label}`;
});
const previewItemIsPdf = computed(
    () => previewItem.value?.mime_type === 'application/pdf',
);
const previewItemIsImage = computed(
    () => previewItem.value?.mime_type.startsWith('image/') ?? false,
);
const previewDialogTitle = computed(() =>
    previewItem.value ? `Preview ${previewItem.value.name}` : 'Preview invoice',
);
const createFolderSubmitDisabled = computed(() => {
    if (createFolderForm.processing) return true;

    return createFolderForm.folder_key.trim() === '';
});

watch(
    () => selectedMonth.value,
    (nextMonth) => {
        uploadForm.billing_period =
            nextMonth ?? props.invoiceArchive.current_period;
    },
    { immediate: true },
);

watch(
    yearFolders,
    (folders) => {
        if (
            selectedYear.value &&
            !folders.some((folder) => folder.key === selectedYear.value)
        ) {
            selectedYear.value = null;
            selectedMonth.value = null;
        }

        if (
            selectedMonth.value &&
            !folders.some((folder) =>
                folder.months.some(
                    (month) => month.key === selectedMonth.value,
                ),
            )
        ) {
            selectedMonth.value = null;
        }
    },
    { immediate: true },
);

watch(folderActionDialogOpen, (isOpen) => {
    if (isOpen) {
        return;
    }

    activeFolderAction.value = 'rename';
    activeFolderTarget.value = null;
    folderActionForm.clearErrors();
    folderActionForm.reset();
    folderActionForm.folder_type = 'year';
});

watch(folderInfoDialogOpen, (isOpen) => {
    if (isOpen) {
        return;
    }

    folderInfoTarget.value = null;
});

watch(previewDialogOpen, (isOpen) => {
    if (isOpen) {
        return;
    }

    previewItem.value = null;
});

watch(createFolderDialogOpen, (isOpen) => {
    if (isOpen) {
        return;
    }

    createFolderForm.clearErrors();
    createFolderForm.reset();
    createFolderForm.folder_type =
        selectedYear.value || selectedMonth.value ? 'period' : 'year';
});

function formatMonthLabel(period: string): string {
    const [year, month] = period.split('-');
    const date = new Date(Number(year), Number(month) - 1, 1);

    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        year: 'numeric',
    }).format(date);
}

function sortYearFolders(left: YearFolder, right: YearFolder): number {
    switch (folderSortMode.value) {
        case 'oldest':
            return Number(left.key) - Number(right.key);
        case 'name-asc':
            return left.key.localeCompare(right.key);
        case 'name-desc':
            return right.key.localeCompare(left.key);
        case 'size-desc':
            return (
                right.totalBytes - left.totalBytes ||
                Number(right.key) - Number(left.key)
            );
        case 'newest':
        default:
            return Number(right.key) - Number(left.key);
    }
}

function sortMonthFolders(months: MonthFolder[]): MonthFolder[] {
    return [...months].sort((left, right) => {
        switch (folderSortMode.value) {
            case 'oldest':
                return left.key.localeCompare(right.key);
            case 'name-asc':
                return left.label.localeCompare(right.label);
            case 'name-desc':
                return right.label.localeCompare(left.label);
            case 'size-desc':
                return (
                    right.totalBytes - left.totalBytes ||
                    right.key.localeCompare(left.key)
                );
            case 'newest':
            default:
                return right.key.localeCompare(left.key);
        }
    });
}

function sortArchiveItems(items: ArchiveItem[]): ArchiveItem[] {
    return [...items].sort((left, right) => {
        switch (folderSortMode.value) {
            case 'oldest':
                return (left.uploaded_at ?? '').localeCompare(
                    right.uploaded_at ?? '',
                );
            case 'name-asc':
                return left.name.localeCompare(right.name);
            case 'name-desc':
                return right.name.localeCompare(left.name);
            case 'size-desc':
                return (
                    right.size_bytes - left.size_bytes ||
                    right.name.localeCompare(left.name)
                );
            case 'newest':
            default:
                return (right.uploaded_at ?? '').localeCompare(
                    left.uploaded_at ?? '',
                );
        }
    });
}

function formatBytes(value: number): string {
    if (value < 1024) return `${value} B`;
    if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;

    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

function formatUploadedAt(value?: string | null): string {
    if (!value) return 'Unknown upload date';

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return 'Unknown upload date';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(parsed);
}

function openYear(year: string): void {
    selectedYear.value = year;
    selectedMonth.value = null;
}

function openMonth(period: string): void {
    const year = period.slice(0, 4);

    selectedYear.value = year;
    selectedMonth.value = period;
}

function buildYearFolderTarget(folder: YearFolder): FolderActionTarget {
    return {
        type: 'year',
        key: folder.key,
        label: folder.key,
        fileCount: folder.fileCount,
        totalBytes: folder.totalBytes,
        monthCount: folder.months.length,
        parentYear: null,
    };
}

function buildMonthFolderTarget(folder: MonthFolder): FolderActionTarget {
    return {
        type: 'period',
        key: folder.key,
        label: folder.label,
        fileCount: folder.fileCount,
        totalBytes: folder.totalBytes,
        monthCount: null,
        parentYear: folder.year,
    };
}

function resetExplorer(): void {
    selectedYear.value = null;
    selectedMonth.value = null;
}

function goUpOneLevel(): void {
    if (selectedMonth.value) {
        selectedMonth.value = null;
        return;
    }

    if (selectedYear.value) {
        selectedYear.value = null;
    }
}

function handleFilesPicked(event: Event): void {
    const input = event.currentTarget as HTMLInputElement | null;

    uploadForm.files = Array.from(input?.files ?? []);
}

function resetUploadForm(): void {
    uploadForm.reset();
    uploadForm.billing_period =
        selectedMonth.value ?? props.invoiceArchive.current_period;
    uploadForm.files = [];

    if (uploadInput.value) {
        uploadInput.value.value = '';
    }
}

function defaultFolderKey(type: FolderType): string {
    if (type === 'year') {
        return props.invoiceArchive.current_period.slice(0, 4);
    }

    if (selectedYear.value) {
        return `${selectedYear.value}-${props.invoiceArchive.current_period.slice(5)}`;
    }

    return props.invoiceArchive.current_period;
}

function openCreateFolderDialog(): void {
    const folderType: FolderType =
        selectedYear.value || selectedMonth.value ? 'period' : 'year';

    createFolderForm.clearErrors();
    createFolderForm.reset();
    createFolderForm.folder_type = folderType;
    createFolderForm.folder_key = defaultFolderKey(folderType);
    createFolderDialogOpen.value = true;
}

function submitCreateFolder(): void {
    const nextFolderType = createFolderForm.folder_type;
    const nextFolderKey = createFolderForm.folder_key.trim();

    createFolderForm.folder_key = nextFolderKey;
    createFolderForm.post('/settings/electricity-billing/archive/folders', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (nextFolderType === 'year') {
                selectedYear.value = nextFolderKey;
                selectedMonth.value = null;
            } else {
                selectedYear.value = nextFolderKey.slice(0, 4);
                selectedMonth.value = null;
            }

            createFolderDialogOpen.value = false;
        },
    });
}

function submitUpload(): void {
    uploadForm.post('/settings/electricity-billing/invoices', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploadDialogOpen.value = false;
            resetUploadForm();
        },
    });
}

function destroyInvoice(item: ArchiveItem): void {
    if (!window.confirm(`Delete "${item.name}" from the archive?`)) {
        return;
    }

    router.delete(item.delete_url, {
        preserveScroll: true,
    });
}

function openPreview(item: ArchiveItem): void {
    previewItem.value = item;
    previewDialogOpen.value = true;
}

function openFolderActionDialog(
    action: FolderAction,
    target: FolderActionTarget,
): void {
    activeFolderAction.value = action;
    activeFolderTarget.value = target;
    folderActionForm.clearErrors();
    folderActionForm.folder_type = target.type;
    folderActionForm.folder_key = target.key;
    folderActionForm.target_year = target.type === 'year' ? target.key : '';
    folderActionForm.target_period = target.type === 'period' ? target.key : '';
    folderActionDialogOpen.value = true;
}

function openFolderInfoDialog(target: FolderActionTarget): void {
    folderInfoTarget.value = target;
    folderInfoDialogOpen.value = true;
}

function closeExplorerFlyout(): void {
    explorerFlyout.value = null;
}

function toggleExplorerFlyout(kind: ExplorerFlyoutKind, event: Event): void {
    if (explorerFlyout.value?.kind === kind) {
        closeExplorerFlyout();
        return;
    }

    openExplorerFlyout(kind, event);
}

function openExplorerFlyout(kind: ExplorerFlyoutKind, event: Event): void {
    const trigger = event.currentTarget as HTMLElement | null;
    const content = trigger?.closest(
        '[data-slot="context-menu-content"]',
    ) as HTMLElement | null;

    if (!trigger || !content) {
        return;
    }

    const triggerRect = trigger.getBoundingClientRect();
    const contentRect = content.getBoundingClientRect();
    const gap = 10;
    const spaceRight = window.innerWidth - contentRect.right;
    const spaceLeft = contentRect.left;

    explorerFlyout.value = {
        kind,
        side:
            spaceRight >= explorerFlyoutWidth + gap || spaceRight >= spaceLeft
                ? 'right'
                : 'left',
        top: triggerRect.top - contentRect.top,
    };
}

function submitFolderAction(): void {
    const target = activeFolderTarget.value;

    if (!target) {
        return;
    }

    if (activeFolderAction.value === 'delete') {
        router.delete('/settings/electricity-billing/archive/folders', {
            data: {
                folder_type: target.type,
                folder_key: target.key,
            },
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (target.type === 'year') {
                    selectedYear.value = null;
                    selectedMonth.value = null;
                } else if (selectedMonth.value === target.key) {
                    selectedMonth.value = null;
                }

                folderActionDialogOpen.value = false;
            },
        });

        return;
    }

    const nextYear = folderActionForm.target_year.trim();
    const nextPeriod = folderActionForm.target_period.trim();

    folderActionForm.patch('/settings/electricity-billing/archive/folders', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (target.type === 'year') {
                selectedYear.value = nextYear;
                selectedMonth.value = null;
            } else {
                selectedYear.value = nextPeriod.slice(0, 4);
                selectedMonth.value = nextPeriod;
            }

            folderActionDialogOpen.value = false;
        },
    });
}
</script>

<template>
    <Head title="Invoice archive" />

    <SettingsLayout wide>
        <div class="space-y-4">
            <div
                id="invoice-archive-hero"
                class="rounded-[24px] border border-border/40 bg-gradient-to-br from-background via-background to-muted/10 px-5 py-4 shadow-[0_24px_80px_-64px_rgba(0,0,0,0.9)]"
            >
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div class="space-y-1">
                        <h1 class="text-[1.9rem] font-semibold tracking-tight">
                            Invoice archive
                        </h1>
                        <p class="max-w-3xl text-sm text-muted-foreground">
                            Browse bills by year and month in a cleaner explorer
                            layout with compact folder cards and right-click
                            actions where you need them.
                        </p>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-2 lg:justify-end"
                    >
                        <Button as-child variant="outline" class="rounded-xl">
                            <Link href="/settings/electricity-billing">
                                <ArrowLeft class="h-4 w-4" />
                                Back to billing
                            </Link>
                        </Button>

                        <Dialog v-model:open="uploadDialogOpen">
                            <DialogTrigger as-child>
                                <Button class="rounded-xl">
                                    <HardDriveUpload class="h-4 w-4" />
                                    Upload invoices
                                </Button>
                            </DialogTrigger>

                            <DialogContent class="sm:max-w-xl">
                                <DialogHeader>
                                    <DialogTitle>
                                        Upload previous invoices
                                    </DialogTitle>
                                    <DialogDescription>
                                        Choose the billing month and attach one
                                        or more invoice files. Accepted formats:
                                        {{ acceptedTypesLabel }}.
                                    </DialogDescription>
                                </DialogHeader>

                                <div class="space-y-4">
                                    <div class="space-y-2">
                                        <label
                                            for="billing-period"
                                            class="text-sm font-medium"
                                        >
                                            Billing month
                                        </label>
                                        <Input
                                            id="billing-period"
                                            v-model="uploadForm.billing_period"
                                            type="month"
                                            class="h-11 rounded-xl"
                                        />
                                        <p
                                            v-if="
                                                uploadForm.errors.billing_period
                                            "
                                            class="text-sm text-destructive"
                                        >
                                            {{
                                                uploadForm.errors.billing_period
                                            }}
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <label
                                            for="billing-files"
                                            class="text-sm font-medium"
                                        >
                                            Invoice files
                                        </label>
                                        <Input
                                            id="billing-files"
                                            ref="uploadInput"
                                            type="file"
                                            multiple
                                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                                            class="h-11 rounded-xl pt-2.5"
                                            @change="handleFilesPicked"
                                        />
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Upload PDFs or image scans from
                                            previous monthly bills.
                                        </p>
                                        <p
                                            v-if="uploadForm.errors.files"
                                            class="text-sm text-destructive"
                                        >
                                            {{ uploadForm.errors.files }}
                                        </p>
                                        <p
                                            v-if="uploadForm.errors['files.0']"
                                            class="text-sm text-destructive"
                                        >
                                            {{ uploadForm.errors['files.0'] }}
                                        </p>
                                    </div>
                                </div>

                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="rounded-xl"
                                        :disabled="uploadForm.processing"
                                        @click="uploadDialogOpen = false"
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="button"
                                        class="rounded-xl"
                                        :disabled="
                                            uploadForm.processing ||
                                            uploadForm.files.length === 0
                                        "
                                        @click="submitUpload"
                                    >
                                        Upload
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>
            </div>

            <Card
                id="invoice-archive-explorer"
                class="mt-2 gap-1 overflow-hidden rounded-[28px] border border-border/50 bg-card/95 py-1 shadow-[0_28px_90px_-70px_rgba(0,0,0,0.95)]"
            >
                <CardContent class="p-0">
                    <template v-if="!hasArchiveFolders">
                        <div class="p-6">
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <FolderCode />
                                    </EmptyMedia>
                                    <EmptyTitle
                                        >No archive folders yet</EmptyTitle
                                    >
                                    <EmptyDescription>
                                        Start with a year folder, then add month
                                        folders and upload your previous
                                        electricity bills into a cleaner archive
                                        explorer.
                                    </EmptyDescription>
                                </EmptyHeader>
                                <EmptyContent>
                                    <Button
                                        type="button"
                                        class="rounded-xl"
                                        @click="openCreateFolderDialog"
                                    >
                                        <FolderPlus class="h-4 w-4" />
                                        New year folder
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="rounded-xl"
                                        @click="uploadDialogOpen = true"
                                    >
                                        <HardDriveUpload class="h-4 w-4" />
                                        Upload invoices
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        class="rounded-xl"
                                        as-child
                                    >
                                        <Link
                                            href="/settings/electricity-billing"
                                        >
                                            Back to billing
                                        </Link>
                                    </Button>
                                </EmptyContent>
                                <Button
                                    variant="link"
                                    as-child
                                    class="text-muted-foreground"
                                    size="sm"
                                >
                                    <Link href="/settings/electricity-billing">
                                        Billing settings
                                        <ArrowUpRight class="h-4 w-4" />
                                    </Link>
                                </Button>
                            </Empty>
                        </div>
                    </template>

                    <template v-else>
                        <div
                            class="grid min-h-[25rem] items-stretch gap-0 xl:grid-cols-[248px_minmax(0,1fr)]"
                        >
                            <aside
                                class="border-b border-border/30 bg-muted/15 p-3 xl:border-r xl:border-b-0"
                            >
                                <div
                                    class="light-outline-strong h-full min-h-[calc(100vh-19.5rem)] rounded-[26px] border border-border/40 bg-gradient-to-b from-background/95 to-muted/10 p-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.04)] xl:sticky xl:top-4"
                                >
                                    <div class="mb-3 px-1">
                                        <p
                                            class="text-[11px] font-medium tracking-[0.24em] text-muted-foreground uppercase"
                                        >
                                            Archive folders
                                        </p>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            Fixed navigator with cleaner folder
                                            levels.
                                        </p>
                                    </div>

                                    <div
                                        class="space-y-2 overflow-y-auto xl:max-h-[calc(100vh-25rem)] xl:pr-1"
                                    >
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-3 rounded-[20px] border px-3 py-3 text-left text-sm transition"
                                            :class="
                                                !selectedYear
                                                    ? 'border-primary/25 bg-primary/10 font-medium text-primary shadow-[0_12px_30px_-24px_rgba(214,229,126,0.9)]'
                                                    : 'light-outline text-muted-foreground hover:border-border/50 hover:bg-background/70 hover:text-foreground'
                                            "
                                            @click="resetExplorer"
                                        >
                                            <span
                                                class="light-outline-soft flex h-9 w-9 items-center justify-center rounded-2xl border border-border/40 bg-background/80"
                                            >
                                                <ReceiptText class="h-4 w-4" />
                                            </span>
                                            <span class="flex-1"
                                                >All invoices</span
                                            >
                                        </button>

                                        <div
                                            v-for="year in yearFolders"
                                            :key="year.key"
                                            class="light-outline rounded-[22px] border border-border/30 bg-background/35 p-1.5"
                                        >
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-3 rounded-[18px] border px-3 py-3 text-left text-sm transition"
                                                :class="
                                                    selectedYear === year.key &&
                                                    !selectedMonth
                                                        ? 'border-primary/25 bg-primary/10 font-medium text-primary'
                                                        : 'light-outline text-muted-foreground hover:border-border/50 hover:bg-background/80 hover:text-foreground'
                                                "
                                                @click="openYear(year.key)"
                                            >
                                                <span
                                                    class="light-outline-soft flex h-9 w-9 items-center justify-center rounded-2xl border border-border/40 bg-background/80"
                                                >
                                                    <FolderOpen
                                                        class="h-4 w-4"
                                                    />
                                                </span>
                                                <span class="flex-1">
                                                    {{ year.key }}
                                                </span>
                                                <span
                                                    class="rounded-full bg-muted px-2 py-0.5 text-[11px]"
                                                >
                                                    {{ year.fileCount }}
                                                </span>
                                            </button>

                                            <div
                                                v-if="selectedYear === year.key"
                                                class="mt-1 space-y-1.5 pl-3"
                                            >
                                                <button
                                                    v-for="month in year.months"
                                                    :key="month.key"
                                                    type="button"
                                                    class="flex w-full items-center gap-3 rounded-[16px] border px-3 py-2.5 text-left text-sm transition"
                                                    :class="
                                                        selectedMonth ===
                                                        month.key
                                                            ? 'border-primary/20 bg-primary/10 font-medium text-primary'
                                                            : 'light-outline-soft text-muted-foreground hover:border-border/40 hover:bg-background/60 hover:text-foreground'
                                                    "
                                                    @click="
                                                        openMonth(month.key)
                                                    "
                                                >
                                                    <CalendarRange
                                                        class="h-4 w-4"
                                                    />
                                                    <span
                                                        class="flex-1 truncate"
                                                    >
                                                        {{ month.label }}
                                                    </span>
                                                    <span class="text-[11px]">
                                                        {{ month.fileCount }}
                                                    </span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </aside>

                            <div class="p-3 pl-0">
                                <div
                                    class="light-outline-strong flex h-full min-h-[calc(100vh-19.5rem)] flex-col overflow-hidden rounded-[30px] border border-border/35 bg-background/35 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]"
                                >
                                    <div
                                        class="rounded-t-[30px] border-b border-border/30 bg-background/70 px-4 py-3.5 sm:px-5"
                                    >
                                        <div
                                            class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                                        >
                                            <div class="space-y-2">
                                                <div
                                                    class="flex flex-wrap items-center gap-2 text-sm"
                                                >
                                                    <button
                                                        type="button"
                                                        class="rounded-full bg-muted/60 px-3 py-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                        @click="resetExplorer"
                                                    >
                                                        Archive
                                                    </button>
                                                    <ChevronRight
                                                        class="h-4 w-4 text-muted-foreground"
                                                    />
                                                    <button
                                                        v-if="selectedYear"
                                                        type="button"
                                                        class="rounded-full bg-muted/60 px-3 py-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                        @click="
                                                            selectedMonth
                                                                ? (selectedMonth =
                                                                      null)
                                                                : resetExplorer()
                                                        "
                                                    >
                                                        {{ selectedYear }}
                                                    </button>
                                                    <template
                                                        v-if="selectedMonth"
                                                    >
                                                        <ChevronRight
                                                            class="h-4 w-4 text-muted-foreground"
                                                        />
                                                        <span
                                                            class="rounded-full bg-primary/10 px-3 py-1 text-primary"
                                                        >
                                                            {{
                                                                activeMonthFolder?.label
                                                            }}
                                                        </span>
                                                    </template>
                                                </div>

                                                <div class="space-y-1">
                                                    <h2
                                                        class="text-xl font-semibold tracking-tight"
                                                    >
                                                        {{ explorerPanelTitle }}
                                                    </h2>
                                                    <p
                                                        class="text-sm text-muted-foreground"
                                                    >
                                                        {{
                                                            explorerPanelDescription
                                                        }}
                                                    </p>
                                                </div>
                                            </div>

                                            <div
                                                class="flex flex-wrap items-center gap-2 lg:justify-end"
                                            >
                                                <Button
                                                    v-if="
                                                        selectedYear ||
                                                        selectedMonth
                                                    "
                                                    type="button"
                                                    variant="outline"
                                                    class="rounded-xl"
                                                    @click="goUpOneLevel"
                                                >
                                                    <ArrowLeft
                                                        class="h-4 w-4"
                                                    />
                                                    Up one level
                                                </Button>
                                            </div>
                                        </div>

                                        <div
                                            class="mt-3 text-xs text-muted-foreground"
                                        >
                                            <span>
                                                Right-click empty explorer space
                                                for quick actions.
                                            </span>
                                        </div>
                                    </div>

                                    <ContextMenu>
                                        <ContextMenuTrigger as-child>
                                            <div
                                                class="flex-1 p-3 pb-5 sm:p-4 sm:pb-6"
                                            >
                                                <template v-if="!selectedYear">
                                                    <div
                                                        v-if="
                                                            folderViewMode ===
                                                            'grid'
                                                        "
                                                        class="grid auto-rows-fr gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
                                                    >
                                                        <ContextMenu
                                                            v-for="year in yearFolders"
                                                            :key="year.key"
                                                        >
                                                            <ContextMenuTrigger
                                                                as-child
                                                            >
                                                                <button
                                                                    type="button"
                                                                    class="group relative flex min-h-[138px] flex-col overflow-hidden rounded-[22px] border border-border/40 bg-gradient-to-br from-background via-background to-muted/20 p-4 text-left transition duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-[0_24px_54px_-44px_rgba(0,0,0,0.9)]"
                                                                    @click="
                                                                        openYear(
                                                                            year.key,
                                                                        )
                                                                    "
                                                                >
                                                                    <div
                                                                        class="absolute inset-x-0 top-0 h-16 bg-gradient-to-b from-primary/10 to-transparent opacity-70"
                                                                    />
                                                                    <FolderOpen
                                                                        class="relative h-6 w-6 text-primary"
                                                                    />
                                                                    <p
                                                                        class="relative mt-4 text-lg font-semibold"
                                                                    >
                                                                        {{
                                                                            year.key
                                                                        }}
                                                                    </p>
                                                                    <p
                                                                        class="relative mt-1.5 text-sm text-muted-foreground"
                                                                    >
                                                                        {{
                                                                            year.fileCount
                                                                        }}
                                                                        files
                                                                    </p>
                                                                    <div
                                                                        class="relative mt-auto pt-4 text-xs text-muted-foreground"
                                                                    >
                                                                        {{
                                                                            formatBytes(
                                                                                year.totalBytes,
                                                                            )
                                                                        }}
                                                                    </div>
                                                                </button>
                                                            </ContextMenuTrigger>

                                                            <ContextMenuContent
                                                                :class="
                                                                    contextMenuContentClass
                                                                "
                                                            >
                                                                <ContextMenuLabel
                                                                    :class="
                                                                        contextMenuLabelClass
                                                                    "
                                                                >
                                                                    {{
                                                                        year.key
                                                                    }}
                                                                </ContextMenuLabel>
                                                                <ContextMenuItem
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openYear(
                                                                            year.key,
                                                                        )
                                                                    "
                                                                >
                                                                    <FolderOpen />
                                                                    Open folder
                                                                </ContextMenuItem>
                                                                <ContextMenuItem
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openFolderInfoDialog(
                                                                            buildYearFolderTarget(
                                                                                year,
                                                                            ),
                                                                        )
                                                                    "
                                                                >
                                                                    <Info />
                                                                    Get info
                                                                </ContextMenuItem>
                                                                <ContextMenuSeparator
                                                                    :class="
                                                                        contextMenuSeparatorClass
                                                                    "
                                                                />
                                                                <ContextMenuItem
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openFolderActionDialog(
                                                                            'rename',
                                                                            buildYearFolderTarget(
                                                                                year,
                                                                            ),
                                                                        )
                                                                    "
                                                                >
                                                                    <Pencil />
                                                                    Rename
                                                                    folder
                                                                </ContextMenuItem>
                                                                <ContextMenuItem
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openFolderActionDialog(
                                                                            'move',
                                                                            buildYearFolderTarget(
                                                                                year,
                                                                            ),
                                                                        )
                                                                    "
                                                                >
                                                                    <ArrowRightLeft />
                                                                    Move folder
                                                                </ContextMenuItem>
                                                                <ContextMenuSeparator
                                                                    :class="
                                                                        contextMenuSeparatorClass
                                                                    "
                                                                />
                                                                <ContextMenuItem
                                                                    variant="destructive"
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openFolderActionDialog(
                                                                            'delete',
                                                                            buildYearFolderTarget(
                                                                                year,
                                                                            ),
                                                                        )
                                                                    "
                                                                >
                                                                    <Trash2 />
                                                                    Delete
                                                                    folder
                                                                </ContextMenuItem>
                                                            </ContextMenuContent>
                                                        </ContextMenu>
                                                    </div>

                                                    <div
                                                        v-else
                                                        class="space-y-3"
                                                    >
                                                        <ContextMenu
                                                            v-for="year in yearFolders"
                                                            :key="year.key"
                                                        >
                                                            <ContextMenuTrigger
                                                                as-child
                                                            >
                                                                <button
                                                                    type="button"
                                                                    class="flex w-full items-center gap-4 rounded-[22px] border border-border/40 bg-background/70 px-4 py-4 text-left transition hover:border-primary/30 hover:bg-background"
                                                                    @click="
                                                                        openYear(
                                                                            year.key,
                                                                        )
                                                                    "
                                                                >
                                                                    <span
                                                                        class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                                                                    >
                                                                        <FolderOpen
                                                                            class="h-5 w-5"
                                                                        />
                                                                    </span>
                                                                    <span
                                                                        class="min-w-0 flex-1"
                                                                    >
                                                                        <span
                                                                            class="block text-sm font-medium"
                                                                        >
                                                                            {{
                                                                                year.key
                                                                            }}
                                                                        </span>
                                                                        <span
                                                                            class="block text-xs text-muted-foreground"
                                                                        >
                                                                            {{
                                                                                year.fileCount
                                                                            }}
                                                                            files
                                                                            ·
                                                                            {{
                                                                                formatBytes(
                                                                                    year.totalBytes,
                                                                                )
                                                                            }}
                                                                        </span>
                                                                    </span>
                                                                    <ChevronRight
                                                                        class="h-4 w-4 text-muted-foreground"
                                                                    />
                                                                </button>
                                                            </ContextMenuTrigger>

                                                            <ContextMenuContent
                                                                :class="
                                                                    contextMenuContentClass
                                                                "
                                                            >
                                                                <ContextMenuLabel
                                                                    :class="
                                                                        contextMenuLabelClass
                                                                    "
                                                                >
                                                                    {{
                                                                        year.key
                                                                    }}
                                                                </ContextMenuLabel>
                                                                <ContextMenuItem
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openYear(
                                                                            year.key,
                                                                        )
                                                                    "
                                                                >
                                                                    <FolderOpen />
                                                                    Open folder
                                                                </ContextMenuItem>
                                                                <ContextMenuItem
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openFolderInfoDialog(
                                                                            buildYearFolderTarget(
                                                                                year,
                                                                            ),
                                                                        )
                                                                    "
                                                                >
                                                                    <Info />
                                                                    Get info
                                                                </ContextMenuItem>
                                                                <ContextMenuSeparator
                                                                    :class="
                                                                        contextMenuSeparatorClass
                                                                    "
                                                                />
                                                                <ContextMenuItem
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openFolderActionDialog(
                                                                            'rename',
                                                                            buildYearFolderTarget(
                                                                                year,
                                                                            ),
                                                                        )
                                                                    "
                                                                >
                                                                    <Pencil />
                                                                    Rename
                                                                    folder
                                                                </ContextMenuItem>
                                                                <ContextMenuItem
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openFolderActionDialog(
                                                                            'move',
                                                                            buildYearFolderTarget(
                                                                                year,
                                                                            ),
                                                                        )
                                                                    "
                                                                >
                                                                    <ArrowRightLeft />
                                                                    Move folder
                                                                </ContextMenuItem>
                                                                <ContextMenuSeparator
                                                                    :class="
                                                                        contextMenuSeparatorClass
                                                                    "
                                                                />
                                                                <ContextMenuItem
                                                                    variant="destructive"
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openFolderActionDialog(
                                                                            'delete',
                                                                            buildYearFolderTarget(
                                                                                year,
                                                                            ),
                                                                        )
                                                                    "
                                                                >
                                                                    <Trash2 />
                                                                    Delete
                                                                    folder
                                                                </ContextMenuItem>
                                                            </ContextMenuContent>
                                                        </ContextMenu>
                                                    </div>
                                                </template>

                                                <template
                                                    v-else-if="
                                                        selectedYear &&
                                                        !selectedMonth
                                                    "
                                                >
                                                    <div
                                                        v-if="
                                                            folderViewMode ===
                                                            'grid'
                                                        "
                                                        class="grid auto-rows-fr gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
                                                    >
                                                        <ContextMenu
                                                            v-for="month in activeYearFolder?.months ??
                                                            []"
                                                            :key="month.key"
                                                        >
                                                            <ContextMenuTrigger
                                                                as-child
                                                            >
                                                                <button
                                                                    type="button"
                                                                    class="group relative flex min-h-[138px] flex-col overflow-hidden rounded-[22px] border border-border/40 bg-gradient-to-br from-background via-background to-muted/20 p-4 text-left transition duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-[0_24px_54px_-44px_rgba(0,0,0,0.9)]"
                                                                    @click="
                                                                        openMonth(
                                                                            month.key,
                                                                        )
                                                                    "
                                                                >
                                                                    <div
                                                                        class="absolute inset-x-0 top-0 h-16 bg-gradient-to-b from-primary/10 to-transparent opacity-70"
                                                                    />
                                                                    <FolderOpen
                                                                        class="relative h-6 w-6 text-primary"
                                                                    />
                                                                    <p
                                                                        class="relative mt-4 text-lg font-semibold"
                                                                    >
                                                                        {{
                                                                            month.label
                                                                        }}
                                                                    </p>
                                                                    <p
                                                                        class="relative mt-1.5 text-sm text-muted-foreground"
                                                                    >
                                                                        {{
                                                                            month.fileCount
                                                                        }}
                                                                        files
                                                                    </p>
                                                                    <div
                                                                        class="relative mt-auto pt-4 text-xs text-muted-foreground"
                                                                    >
                                                                        {{
                                                                            formatBytes(
                                                                                month.totalBytes,
                                                                            )
                                                                        }}
                                                                    </div>
                                                                </button>
                                                            </ContextMenuTrigger>

                                                            <ContextMenuContent
                                                                :class="
                                                                    contextMenuContentClass
                                                                "
                                                            >
                                                                <ContextMenuLabel
                                                                    :class="
                                                                        contextMenuLabelClass
                                                                    "
                                                                >
                                                                    {{
                                                                        month.label
                                                                    }}
                                                                </ContextMenuLabel>
                                                                <ContextMenuItem
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openMonth(
                                                                            month.key,
                                                                        )
                                                                    "
                                                                >
                                                                    <FolderOpen />
                                                                    Open folder
                                                                </ContextMenuItem>
                                                                <ContextMenuItem
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openFolderInfoDialog(
                                                                            buildMonthFolderTarget(
                                                                                month,
                                                                            ),
                                                                        )
                                                                    "
                                                                >
                                                                    <Info />
                                                                    Get info
                                                                </ContextMenuItem>
                                                                <ContextMenuSeparator
                                                                    :class="
                                                                        contextMenuSeparatorClass
                                                                    "
                                                                />
                                                                <ContextMenuItem
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openFolderActionDialog(
                                                                            'rename',
                                                                            buildMonthFolderTarget(
                                                                                month,
                                                                            ),
                                                                        )
                                                                    "
                                                                >
                                                                    <Pencil />
                                                                    Rename
                                                                    folder
                                                                </ContextMenuItem>
                                                                <ContextMenuItem
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openFolderActionDialog(
                                                                            'move',
                                                                            buildMonthFolderTarget(
                                                                                month,
                                                                            ),
                                                                        )
                                                                    "
                                                                >
                                                                    <ArrowRightLeft />
                                                                    Move folder
                                                                </ContextMenuItem>
                                                                <ContextMenuSeparator
                                                                    :class="
                                                                        contextMenuSeparatorClass
                                                                    "
                                                                />
                                                                <ContextMenuItem
                                                                    variant="destructive"
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openFolderActionDialog(
                                                                            'delete',
                                                                            buildMonthFolderTarget(
                                                                                month,
                                                                            ),
                                                                        )
                                                                    "
                                                                >
                                                                    <Trash2 />
                                                                    Delete
                                                                    folder
                                                                </ContextMenuItem>
                                                            </ContextMenuContent>
                                                        </ContextMenu>
                                                    </div>

                                                    <div
                                                        v-else
                                                        class="space-y-3"
                                                    >
                                                        <ContextMenu
                                                            v-for="month in activeYearFolder?.months ??
                                                            []"
                                                            :key="month.key"
                                                        >
                                                            <ContextMenuTrigger
                                                                as-child
                                                            >
                                                                <button
                                                                    type="button"
                                                                    class="flex w-full items-center gap-4 rounded-[22px] border border-border/40 bg-background/70 px-4 py-4 text-left transition hover:border-primary/30 hover:bg-background"
                                                                    @click="
                                                                        openMonth(
                                                                            month.key,
                                                                        )
                                                                    "
                                                                >
                                                                    <span
                                                                        class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                                                                    >
                                                                        <CalendarRange
                                                                            class="h-5 w-5"
                                                                        />
                                                                    </span>
                                                                    <span
                                                                        class="min-w-0 flex-1"
                                                                    >
                                                                        <span
                                                                            class="block text-sm font-medium"
                                                                        >
                                                                            {{
                                                                                month.label
                                                                            }}
                                                                        </span>
                                                                        <span
                                                                            class="block text-xs text-muted-foreground"
                                                                        >
                                                                            {{
                                                                                month.fileCount
                                                                            }}
                                                                            files
                                                                            ·
                                                                            {{
                                                                                formatBytes(
                                                                                    month.totalBytes,
                                                                                )
                                                                            }}
                                                                        </span>
                                                                    </span>
                                                                    <ChevronRight
                                                                        class="h-4 w-4 text-muted-foreground"
                                                                    />
                                                                </button>
                                                            </ContextMenuTrigger>

                                                            <ContextMenuContent
                                                                :class="
                                                                    contextMenuContentClass
                                                                "
                                                            >
                                                                <ContextMenuLabel
                                                                    :class="
                                                                        contextMenuLabelClass
                                                                    "
                                                                >
                                                                    {{
                                                                        month.label
                                                                    }}
                                                                </ContextMenuLabel>
                                                                <ContextMenuItem
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openMonth(
                                                                            month.key,
                                                                        )
                                                                    "
                                                                >
                                                                    <FolderOpen />
                                                                    Open folder
                                                                </ContextMenuItem>
                                                                <ContextMenuItem
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openFolderInfoDialog(
                                                                            buildMonthFolderTarget(
                                                                                month,
                                                                            ),
                                                                        )
                                                                    "
                                                                >
                                                                    <Info />
                                                                    Get info
                                                                </ContextMenuItem>
                                                                <ContextMenuSeparator
                                                                    :class="
                                                                        contextMenuSeparatorClass
                                                                    "
                                                                />
                                                                <ContextMenuItem
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openFolderActionDialog(
                                                                            'rename',
                                                                            buildMonthFolderTarget(
                                                                                month,
                                                                            ),
                                                                        )
                                                                    "
                                                                >
                                                                    <Pencil />
                                                                    Rename
                                                                    folder
                                                                </ContextMenuItem>
                                                                <ContextMenuItem
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openFolderActionDialog(
                                                                            'move',
                                                                            buildMonthFolderTarget(
                                                                                month,
                                                                            ),
                                                                        )
                                                                    "
                                                                >
                                                                    <ArrowRightLeft />
                                                                    Move folder
                                                                </ContextMenuItem>
                                                                <ContextMenuSeparator
                                                                    :class="
                                                                        contextMenuSeparatorClass
                                                                    "
                                                                />
                                                                <ContextMenuItem
                                                                    variant="destructive"
                                                                    :class="
                                                                        contextMenuItemClass
                                                                    "
                                                                    @select="
                                                                        openFolderActionDialog(
                                                                            'delete',
                                                                            buildMonthFolderTarget(
                                                                                month,
                                                                            ),
                                                                        )
                                                                    "
                                                                >
                                                                    <Trash2 />
                                                                    Delete
                                                                    folder
                                                                </ContextMenuItem>
                                                            </ContextMenuContent>
                                                        </ContextMenu>
                                                    </div>
                                                </template>

                                                <template v-else>
                                                    <div
                                                        class="overflow-hidden rounded-[26px] border border-border/40 bg-background/60"
                                                    >
                                                        <div
                                                            class="grid grid-cols-[minmax(0,1.7fr)_120px_180px_auto] gap-3 border-b border-border/30 bg-muted/20 px-5 py-4 text-xs tracking-[0.14em] text-muted-foreground uppercase"
                                                        >
                                                            <span>Name</span>
                                                            <span>Type</span>
                                                            <span
                                                                >Uploaded</span
                                                            >
                                                            <span
                                                                class="text-right"
                                                            >
                                                                Actions
                                                            </span>
                                                        </div>

                                                        <div
                                                            v-for="item in activeMonthFolder?.items ??
                                                            []"
                                                            :key="item.id"
                                                            class="grid grid-cols-[minmax(0,1.7fr)_120px_180px_auto] items-center gap-3 border-b border-border/20 px-5 py-4 last:border-b-0"
                                                        >
                                                            <div
                                                                class="min-w-0"
                                                            >
                                                                <div
                                                                    class="flex items-center gap-3"
                                                                >
                                                                    <div
                                                                        class="rounded-2xl bg-primary/10 p-3 text-primary"
                                                                    >
                                                                        <FileText
                                                                            class="h-4 w-4"
                                                                        />
                                                                    </div>
                                                                    <div
                                                                        class="min-w-0"
                                                                    >
                                                                        <p
                                                                            class="truncate text-sm font-medium"
                                                                        >
                                                                            {{
                                                                                item.name
                                                                            }}
                                                                        </p>
                                                                        <p
                                                                            class="text-xs text-muted-foreground"
                                                                        >
                                                                            {{
                                                                                formatBytes(
                                                                                    item.size_bytes,
                                                                                )
                                                                            }}
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <span
                                                                class="text-sm text-muted-foreground"
                                                            >
                                                                {{
                                                                    item.extension
                                                                        ? item.extension.toUpperCase()
                                                                        : 'FILE'
                                                                }}
                                                            </span>
                                                            <span
                                                                class="text-sm text-muted-foreground"
                                                            >
                                                                {{
                                                                    formatUploadedAt(
                                                                        item.uploaded_at,
                                                                    )
                                                                }}
                                                            </span>

                                                            <div
                                                                class="flex items-center justify-end gap-2"
                                                            >
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    class="rounded-xl"
                                                                    @click="
                                                                        openPreview(
                                                                            item,
                                                                        )
                                                                    "
                                                                >
                                                                    <Eye
                                                                        class="h-4 w-4"
                                                                    />
                                                                    Preview
                                                                </Button>
                                                                <Button
                                                                    as="a"
                                                                    :href="
                                                                        item.download_url
                                                                    "
                                                                    variant="outline"
                                                                    class="rounded-xl"
                                                                >
                                                                    <Download
                                                                        class="h-4 w-4"
                                                                    />
                                                                    Download
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    class="rounded-xl text-destructive hover:text-destructive"
                                                                    @click="
                                                                        destroyInvoice(
                                                                            item,
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
                                                </template>
                                            </div>
                                        </ContextMenuTrigger>

                                        <ContextMenuContent
                                            :class="[
                                                contextMenuContentClass,
                                                'overflow-visible',
                                            ]"
                                        >
                                            <ContextMenuLabel
                                                :class="contextMenuLabelClass"
                                            >
                                                Explorer actions
                                            </ContextMenuLabel>
                                            <ContextMenuItem
                                                :class="contextMenuItemClass"
                                                @pointerenter="
                                                    closeExplorerFlyout
                                                "
                                                @select="openCreateFolderDialog"
                                            >
                                                <FolderPlus />
                                                {{ createFolderActionLabel }}
                                            </ContextMenuItem>
                                            <ContextMenuItem
                                                :class="contextMenuItemClass"
                                                @pointerenter="
                                                    closeExplorerFlyout
                                                "
                                                @select="
                                                    uploadDialogOpen = true
                                                "
                                            >
                                                <HardDriveUpload />
                                                Upload invoices
                                            </ContextMenuItem>
                                            <ContextMenuSeparator
                                                :class="
                                                    contextMenuSeparatorClass
                                                "
                                            />
                                            <ContextMenuItem
                                                :class="contextMenuItemClass"
                                                v-if="
                                                    selectedYear ||
                                                    selectedMonth
                                                "
                                                @pointerenter="
                                                    closeExplorerFlyout
                                                "
                                                @select="goUpOneLevel"
                                            >
                                                <ArrowLeft />
                                                Up one level
                                            </ContextMenuItem>
                                            <ContextMenuItem
                                                :class="contextMenuItemClass"
                                                v-if="
                                                    selectedYear ||
                                                    selectedMonth
                                                "
                                                @pointerenter="
                                                    closeExplorerFlyout
                                                "
                                                @select="resetExplorer"
                                            >
                                                <ReceiptText />
                                                Archive root
                                            </ContextMenuItem>
                                            <ContextMenuSeparator
                                                :class="
                                                    contextMenuSeparatorClass
                                                "
                                                v-if="
                                                    selectedYear ||
                                                    selectedMonth
                                                "
                                            />
                                            <ContextMenuSeparator
                                                :class="
                                                    contextMenuSeparatorClass
                                                "
                                            />
                                            <div
                                                v-if="!selectedMonth"
                                                class="relative"
                                            >
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-left text-[15px] font-medium transition hover:bg-accent hover:text-accent-foreground"
                                                    @mouseenter="
                                                        openExplorerFlyout(
                                                            'view',
                                                            $event,
                                                        )
                                                    "
                                                    @click.prevent="
                                                        toggleExplorerFlyout(
                                                            'view',
                                                            $event,
                                                        )
                                                    "
                                                >
                                                    <LayoutGrid
                                                        class="h-[1.05rem] w-[1.05rem]"
                                                    />
                                                    <span>View mode</span>
                                                    <span
                                                        class="ml-auto max-w-[6rem] truncate text-xs text-muted-foreground"
                                                    >
                                                        {{
                                                            folderViewModeLabel
                                                        }}
                                                    </span>
                                                    <ChevronRight
                                                        class="h-4 w-4"
                                                    />
                                                </button>
                                            </div>
                                            <div class="relative">
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-left text-[15px] font-medium transition hover:bg-accent hover:text-accent-foreground"
                                                    @mouseenter="
                                                        openExplorerFlyout(
                                                            'sort',
                                                            $event,
                                                        )
                                                    "
                                                    @click.prevent="
                                                        toggleExplorerFlyout(
                                                            'sort',
                                                            $event,
                                                        )
                                                    "
                                                >
                                                    <ArrowUpDown
                                                        class="h-[1.05rem] w-[1.05rem]"
                                                    />
                                                    <span>Sort folders</span>
                                                    <span
                                                        class="ml-auto max-w-[8rem] truncate text-xs text-muted-foreground"
                                                    >
                                                        {{ folderSortLabel }}
                                                    </span>
                                                    <ChevronRight
                                                        class="h-4 w-4"
                                                    />
                                                </button>
                                            </div>

                                            <div
                                                v-if="explorerFlyout"
                                                class="absolute top-0 z-[80] w-64 rounded-[22px] border border-border/60 bg-background/95 p-2 shadow-[0_24px_80px_-36px_rgba(0,0,0,0.92)] backdrop-blur-xl"
                                                :class="
                                                    explorerFlyout.side ===
                                                    'right'
                                                        ? 'left-[calc(100%+10px)]'
                                                        : 'right-[calc(100%+10px)]'
                                                "
                                                :style="{
                                                    top: `${explorerFlyout.top}px`,
                                                }"
                                                @mouseenter.stop
                                            >
                                                <ContextMenuLabel
                                                    :class="
                                                        contextMenuLabelClass
                                                    "
                                                >
                                                    {{
                                                        explorerFlyout.kind ===
                                                        'view'
                                                            ? 'Choose view'
                                                            : 'Choose sorting'
                                                    }}
                                                </ContextMenuLabel>

                                                <template
                                                    v-if="
                                                        explorerFlyout.kind ===
                                                        'view'
                                                    "
                                                >
                                                    <button
                                                        type="button"
                                                        class="flex w-full items-center rounded-xl px-3 py-2.5 text-left text-[15px] font-medium transition"
                                                        :class="
                                                            folderViewMode ===
                                                            'grid'
                                                                ? 'bg-accent text-accent-foreground'
                                                                : 'hover:bg-accent hover:text-accent-foreground'
                                                        "
                                                        @click="
                                                            folderViewMode =
                                                                'grid';
                                                            closeExplorerFlyout();
                                                        "
                                                    >
                                                        <span class="ml-6">
                                                            Grid
                                                        </span>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="flex w-full items-center rounded-xl px-3 py-2.5 text-left text-[15px] font-medium transition"
                                                        :class="
                                                            folderViewMode ===
                                                            'list'
                                                                ? 'bg-accent text-accent-foreground'
                                                                : 'hover:bg-accent hover:text-accent-foreground'
                                                        "
                                                        @click="
                                                            folderViewMode =
                                                                'list';
                                                            closeExplorerFlyout();
                                                        "
                                                    >
                                                        <span class="ml-6">
                                                            List
                                                        </span>
                                                    </button>
                                                </template>

                                                <template v-else>
                                                    <button
                                                        v-for="option in folderSortOptions"
                                                        :key="option.value"
                                                        type="button"
                                                        class="flex w-full items-center rounded-xl px-3 py-2.5 text-left text-[15px] font-medium transition"
                                                        :class="
                                                            folderSortMode ===
                                                            option.value
                                                                ? 'bg-accent text-accent-foreground'
                                                                : 'hover:bg-accent hover:text-accent-foreground'
                                                        "
                                                        @click="
                                                            folderSortMode =
                                                                option.value;
                                                            closeExplorerFlyout();
                                                        "
                                                    >
                                                        <span class="ml-6">
                                                            {{ option.label }}
                                                        </span>
                                                    </button>
                                                </template>
                                            </div>
                                        </ContextMenuContent>
                                    </ContextMenu>
                                </div>
                            </div>
                        </div>
                    </template>
                </CardContent>
            </Card>

            <Dialog v-model:open="previewDialogOpen">
                <DialogContent
                    class="max-h-[90vh] overflow-hidden sm:max-w-5xl"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {{ previewDialogTitle }}
                        </DialogTitle>
                        <DialogDescription>
                            Review the invoice directly from the archive before
                            downloading it.
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="previewItem" class="space-y-4">
                        <div
                            class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                        >
                            <span class="rounded-full bg-muted px-3 py-1">
                                {{ previewItem.period }}
                            </span>
                            <span class="rounded-full bg-muted px-3 py-1">
                                {{
                                    previewItem.extension
                                        ? previewItem.extension.toUpperCase()
                                        : 'FILE'
                                }}
                            </span>
                            <span class="rounded-full bg-muted px-3 py-1">
                                {{ formatBytes(previewItem.size_bytes) }}
                            </span>
                        </div>

                        <div
                            class="overflow-hidden rounded-[24px] border border-border/40 bg-muted/20"
                        >
                            <iframe
                                v-if="previewItemIsPdf"
                                :src="previewItem.preview_url"
                                :title="previewItem.name"
                                class="h-[68vh] w-full bg-background"
                            />

                            <div
                                v-else-if="previewItemIsImage"
                                class="flex h-[68vh] items-center justify-center bg-background p-4"
                            >
                                <img
                                    :src="previewItem.preview_url"
                                    :alt="previewItem.name"
                                    class="max-h-full max-w-full rounded-2xl object-contain shadow-[0_20px_60px_-40px_rgba(0,0,0,0.85)]"
                                />
                            </div>

                            <div
                                v-else
                                class="flex h-[50vh] flex-col items-center justify-center gap-3 px-6 text-center"
                            >
                                <FileText
                                    class="h-8 w-8 text-muted-foreground"
                                />
                                <p
                                    class="max-w-md text-sm text-muted-foreground"
                                >
                                    This file type does not support inline
                                    preview yet.
                                </p>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-xl"
                            @click="previewDialogOpen = false"
                        >
                            Close
                        </Button>
                        <Button
                            v-if="previewItem"
                            as="a"
                            :href="previewItem.download_url"
                            class="rounded-xl"
                        >
                            <Download class="h-4 w-4" />
                            Download
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="folderInfoDialogOpen">
                <DialogContent class="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>
                            {{ folderInfoDialogTitle }}
                        </DialogTitle>
                        <DialogDescription>
                            Quick details about the selected archive folder.
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="folderInfoTarget" class="space-y-4">
                        <div
                            class="rounded-[24px] border border-border/40 bg-gradient-to-br from-background via-background to-muted/20 p-5"
                        >
                            <div class="flex items-start gap-4">
                                <div
                                    class="rounded-2xl bg-primary/10 p-3 text-primary"
                                >
                                    <FolderOpen class="h-5 w-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-base font-semibold">
                                        {{ folderInfoTarget.label }}
                                    </p>
                                    <p
                                        class="mt-1 text-sm break-words text-muted-foreground"
                                    >
                                        {{ folderInfoPath }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                class="rounded-2xl border border-border/40 bg-background/60 p-4"
                            >
                                <p
                                    class="text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase"
                                >
                                    Folder type
                                </p>
                                <p class="mt-2 text-sm font-medium">
                                    {{
                                        folderInfoTarget.type === 'year'
                                            ? 'Year folder'
                                            : 'Month folder'
                                    }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-border/40 bg-background/60 p-4"
                            >
                                <p
                                    class="text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase"
                                >
                                    Folder key
                                </p>
                                <p class="mt-2 text-sm font-medium">
                                    {{ folderInfoTarget.key }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-border/40 bg-background/60 p-4"
                            >
                                <div class="flex items-center gap-2">
                                    <Files
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase"
                                    >
                                        Files
                                    </p>
                                </div>
                                <p class="mt-2 text-sm font-medium">
                                    {{ folderInfoTarget.fileCount }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-border/40 bg-background/60 p-4"
                            >
                                <div class="flex items-center gap-2">
                                    <HardDriveUpload
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase"
                                    >
                                        Storage used
                                    </p>
                                </div>
                                <p class="mt-2 text-sm font-medium">
                                    {{
                                        formatBytes(folderInfoTarget.totalBytes)
                                    }}
                                </p>
                            </div>

                            <div
                                v-if="folderInfoTarget.type === 'year'"
                                class="rounded-2xl border border-border/40 bg-background/60 p-4 sm:col-span-2"
                            >
                                <div class="flex items-center gap-2">
                                    <CalendarRange
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase"
                                    >
                                        Contains
                                    </p>
                                </div>
                                <p class="mt-2 text-sm font-medium">
                                    {{ folderInfoTarget.monthCount ?? 0 }} month
                                    folders inside this year.
                                </p>
                            </div>

                            <div
                                v-else
                                class="rounded-2xl border border-border/40 bg-background/60 p-4 sm:col-span-2"
                            >
                                <div class="flex items-center gap-2">
                                    <CalendarRange
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase"
                                    >
                                        Billing period
                                    </p>
                                </div>
                                <p class="mt-2 text-sm font-medium">
                                    {{ folderInfoTarget.key }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-xl"
                            @click="folderInfoDialogOpen = false"
                        >
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="createFolderDialogOpen">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {{ createFolderDialogTitle }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ createFolderDialogDescription }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4">
                        <div
                            class="rounded-2xl border border-border/40 bg-background/60 p-4"
                        >
                            <p class="text-sm font-medium">
                                Create a new {{ createFolderTypeLabel }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{
                                    createFolderForm.folder_type === 'year'
                                        ? 'Use a 4 digit year like 2026.'
                                        : 'Pick the billing month that should appear inside the current year.'
                                }}
                            </p>
                        </div>

                        <div
                            v-if="createFolderForm.folder_type === 'year'"
                            class="space-y-2"
                        >
                            <label
                                for="create-folder-year"
                                class="text-sm font-medium"
                            >
                                Year
                            </label>
                            <Input
                                id="create-folder-year"
                                v-model="createFolderForm.folder_key"
                                type="number"
                                min="2000"
                                max="2999"
                                class="h-11 rounded-xl"
                            />
                        </div>

                        <div v-else class="space-y-2">
                            <label
                                for="create-folder-month"
                                class="text-sm font-medium"
                            >
                                Billing month
                            </label>
                            <Input
                                id="create-folder-month"
                                v-model="createFolderForm.folder_key"
                                type="month"
                                class="h-11 rounded-xl"
                            />
                        </div>

                        <p
                            v-if="createFolderForm.errors.folder_key"
                            class="text-sm text-destructive"
                        >
                            {{ createFolderForm.errors.folder_key }}
                        </p>
                        <p
                            v-if="createFolderForm.errors.folder_type"
                            class="text-sm text-destructive"
                        >
                            {{ createFolderForm.errors.folder_type }}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-xl"
                            :disabled="createFolderForm.processing"
                            @click="createFolderDialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            class="rounded-xl"
                            :disabled="createFolderSubmitDisabled"
                            @click="submitCreateFolder"
                        >
                            {{ createFolderActionLabel }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="folderActionDialogOpen">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {{ folderActionTitle }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ folderActionDescription }}
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="activeFolderTarget" class="space-y-4">
                        <div
                            class="rounded-2xl border border-border/40 bg-background/60 p-4"
                        >
                            <p class="text-sm font-medium">
                                {{ activeFolderTarget.label }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ activeFolderTarget.fileCount }} files ·
                                {{ formatBytes(activeFolderTarget.totalBytes) }}
                            </p>
                        </div>

                        <template v-if="activeFolderAction !== 'delete'">
                            <div
                                v-if="activeFolderTarget.type === 'year'"
                                class="space-y-2"
                            >
                                <label
                                    for="target-year"
                                    class="text-sm font-medium"
                                >
                                    Target year
                                </label>
                                <Input
                                    id="target-year"
                                    v-model="folderActionForm.target_year"
                                    type="number"
                                    min="2000"
                                    max="2999"
                                    class="h-11 rounded-xl"
                                />
                                <p
                                    v-if="folderActionForm.errors.target_year"
                                    class="text-sm text-destructive"
                                >
                                    {{ folderActionForm.errors.target_year }}
                                </p>
                            </div>

                            <div v-else class="space-y-2">
                                <label
                                    for="target-period"
                                    class="text-sm font-medium"
                                >
                                    Target billing month
                                </label>
                                <Input
                                    id="target-period"
                                    v-model="folderActionForm.target_period"
                                    type="month"
                                    class="h-11 rounded-xl"
                                />
                                <p
                                    v-if="folderActionForm.errors.target_period"
                                    class="text-sm text-destructive"
                                >
                                    {{ folderActionForm.errors.target_period }}
                                </p>
                            </div>
                        </template>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-xl"
                            :disabled="folderActionForm.processing"
                            @click="folderActionDialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            class="rounded-xl"
                            :variant="
                                activeFolderAction === 'delete'
                                    ? 'destructive'
                                    : 'default'
                            "
                            :disabled="folderActionSubmitDisabled"
                            @click="submitFolderAction"
                        >
                            {{ folderActionSubmitLabel }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </SettingsLayout>
</template>
