<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import SettingsLayout from '@/layouts/settings/Layout.vue';

type DiagnosticsPayload = {
    latest: Record<string, unknown>;
    connection: {
        mqtt_broker: string;
        mqtt_port: number;
        mqtt_enabled: boolean;
        command_topic: string;
        telemetry_topic: string;
        publish_interval_seconds: number;
        dashboard_poll_seconds: number;
    };
    hardware: {
        device_type: string;
        firmware_version: string;
        relay_count: string;
        ingest_endpoint: string;
        storage: string;
    };
    pinout: Array<{
        name: string;
        pin: string;
        type: string;
    }>;
};

type DetailModalKey =
    | 'connection'
    | 'hardware'
    | 'pinout'
    | 'payload'
    | 'debug';

const props = defineProps<{
    diagnostics: DiagnosticsPayload;
}>();

const activeModal = ref<DetailModalKey | null>(null);

const latestEntries = computed(() =>
    Object.entries(props.diagnostics.latest ?? {}),
);

const rawJson = computed(() =>
    JSON.stringify(props.diagnostics.latest ?? {}, null, 2),
);

const hasRecentData = computed(() =>
    Boolean(props.diagnostics.latest.updated_at),
);

const payloadKeyCount = computed(() => latestEntries.value.length);

const pinoutPreview = computed(() =>
    props.diagnostics.pinout
        .slice(0, 3)
        .map((pin) => `${pin.name} ${pin.pin}`)
        .join(' • '),
);

const payloadPreviewEntries = computed(() =>
    latestEntries.value.slice(0, 4),
);

function openModal(key: DetailModalKey): void {
    activeModal.value = key;
}

function updateModalState(key: DetailModalKey, open: boolean): void {
    activeModal.value = open ? key : null;
}

function formatValue(value: unknown): string {
    if (typeof value === 'boolean') {
        return value ? 'true' : 'false';
    }

    if (value === null || value === undefined) {
        return 'null';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function formatType(value: unknown): string {
    if (value === null) return 'null';
    if (Array.isArray(value)) return 'array';
    return typeof value;
}
</script>

<template>
    <Head title="Hardware & Diagnostics" />

    <h1 class="sr-only">Hardware and diagnostics</h1>

    <SettingsLayout wide>
        <div class="space-y-6 pl-1 lg:pl-3">
            <header class="relative overflow-hidden rounded-[28px] border border-border/40 bg-gradient-to-r from-primary/12 via-primary/6 to-transparent px-6 py-5 shadow-none">
                <div class="absolute inset-y-0 left-0 w-24 bg-gradient-to-r from-primary/10 to-transparent blur-2xl" />
                <div class="relative space-y-2">
                    <h2 class="bg-gradient-to-r from-foreground via-foreground to-primary bg-clip-text text-xl font-semibold tracking-tight text-transparent">
                        Hardware & Diagnostics
                    </h2>
                    <p class="max-w-3xl text-sm text-muted-foreground">
                        A compact overview for the ESP32 strip, with modal panels for MQTT, hardware, pin mapping, and raw diagnostics.
                    </p>
                </div>
            </header>

            <Card class="border-border/40 bg-card/70 shadow-none">
                <CardContent class="space-y-6 p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="space-y-3">
                            <p class="text-xs uppercase tracking-[0.22em] text-muted-foreground">
                                System overview
                            </p>
                            <div class="space-y-2">
                                <p class="text-2xl font-semibold tracking-tight">
                                    {{ diagnostics.hardware.device_type }}
                                </p>
                                <p class="max-w-2xl text-sm text-muted-foreground">
                                    Firmware {{ diagnostics.hardware.firmware_version }} on
                                    {{ diagnostics.connection.mqtt_broker }}:{{ diagnostics.connection.mqtt_port }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <Badge
                                class="rounded-full px-3 py-1"
                                :variant="diagnostics.connection.mqtt_enabled ? 'default' : 'outline'"
                            >
                                {{ diagnostics.connection.mqtt_enabled ? 'MQTT Active' : 'MQTT Disabled' }}
                            </Badge>
                            <Badge
                                class="rounded-full px-3 py-1"
                                :variant="hasRecentData ? 'default' : 'outline'"
                            >
                                {{ hasRecentData ? 'Live data' : 'Waiting for sync' }}
                            </Badge>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-[24px] border border-border/40 bg-background p-4">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">
                                Relay layout
                            </p>
                            <p class="mt-3 text-xl font-semibold">
                                {{ diagnostics.hardware.relay_count }}
                            </p>
                            <p class="mt-2 text-xs leading-5 text-muted-foreground">
                                Physical outputs available on the strip
                            </p>
                        </div>
                        <div class="rounded-[24px] border border-border/40 bg-background p-4">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">
                                Publish cadence
                            </p>
                            <p class="mt-3 text-xl font-semibold">
                                {{ diagnostics.connection.publish_interval_seconds }}s
                            </p>
                            <p class="mt-2 text-xs leading-5 text-muted-foreground">
                                Dashboard poll {{ diagnostics.connection.dashboard_poll_seconds }}s
                            </p>
                        </div>
                        <div class="rounded-[24px] border border-border/40 bg-background p-4">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">
                                Latest sync
                            </p>
                            <p class="mt-3 text-sm font-semibold">
                                {{ hasRecentData ? 'Receiving data' : 'No recent data' }}
                            </p>
                            <p class="mt-2 line-clamp-2 text-xs leading-5 text-muted-foreground">
                                {{ diagnostics.latest.updated_at ?? 'No timestamp available' }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border/40 bg-card/70 shadow-none">
                <CardContent class="space-y-5 p-6">
                    <div class="space-y-2">
                        <p class="text-xs uppercase tracking-[0.22em] text-muted-foreground">
                            Detailed Panels
                        </p>
                        <p class="max-w-2xl text-sm text-muted-foreground">
                            Open only the technical panel you need. The page stays compact, while each detail view gets its own modal.
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div class="rounded-[24px] border border-border/40 bg-background p-5 text-left">
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-2">
                                    <CardTitle class="text-base">Connection</CardTitle>
                                    <CardDescription>
                                        Broker, topics, and telemetry intervals.
                                    </CardDescription>
                                </div>
                                <Badge
                                    :variant="diagnostics.connection.mqtt_enabled ? 'default' : 'outline'"
                                >
                                    {{ diagnostics.connection.mqtt_enabled ? 'MQTT Active' : 'MQTT Disabled' }}
                                </Badge>
                            </div>
                            <div class="mt-5 space-y-1 text-sm">
                                <p class="font-semibold">
                                    {{ diagnostics.connection.mqtt_broker }}:{{ diagnostics.connection.mqtt_port }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Publish {{ diagnostics.connection.publish_interval_seconds }}s
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                class="mt-5 rounded-xl"
                                @click="openModal('connection')"
                            >
                                View connection details
                            </Button>
                        </div>

                        <div class="rounded-[24px] border border-border/40 bg-background p-5 text-left">
                            <div class="space-y-2">
                                <CardTitle class="text-base">Hardware Specs</CardTitle>
                                <CardDescription>
                                    Device model, firmware, storage, and ingest endpoint.
                                </CardDescription>
                            </div>
                            <div class="mt-5 space-y-1 text-sm">
                                <p class="font-semibold">{{ diagnostics.hardware.device_type }}</p>
                                <p class="text-xs text-muted-foreground">
                                    Firmware {{ diagnostics.hardware.firmware_version }}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                class="mt-5 rounded-xl"
                                @click="openModal('hardware')"
                            >
                                View hardware details
                            </Button>
                        </div>

                        <div class="rounded-[24px] border border-border/40 bg-background p-5 text-left">
                            <div class="space-y-2">
                                <CardTitle class="text-base">Sensor Pinout</CardTitle>
                                <CardDescription>
                                    ESP32 pin mapping for analog sensors and relays.
                                </CardDescription>
                            </div>
                            <div class="mt-5 space-y-1 text-sm">
                                <p class="font-semibold">{{ diagnostics.pinout.length }} mapped pins</p>
                                <p class="line-clamp-2 text-xs text-muted-foreground">
                                    {{ pinoutPreview || 'No pinout data available' }}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                class="mt-5 rounded-xl"
                                @click="openModal('pinout')"
                            >
                                View pin mapping
                            </Button>
                        </div>

                        <div class="rounded-[24px] border border-border/40 bg-background p-5 text-left">
                            <div class="space-y-2">
                                <CardTitle class="text-base">JSON Payload</CardTitle>
                                <CardDescription>
                                    Inspect the latest raw snapshot and MQTT topics.
                                </CardDescription>
                            </div>
                            <div class="mt-5 space-y-1 text-sm">
                                <p class="font-semibold">{{ payloadKeyCount }} stored keys</p>
                                <p class="line-clamp-2 break-all font-mono text-[11px] text-muted-foreground">
                                    {{ diagnostics.connection.command_topic }}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                class="mt-5 rounded-xl"
                                @click="openModal('payload')"
                            >
                                View payload data
                            </Button>
                        </div>

                        <div class="rounded-[24px] border border-border/40 bg-background p-5 text-left">
                            <div class="space-y-2">
                                <CardTitle class="text-base">Debug State Table</CardTitle>
                                <CardDescription>
                                    Full typed key-by-key view for the latest payload.
                                </CardDescription>
                            </div>
                            <div class="mt-5 space-y-1 text-sm">
                                <p class="font-semibold">{{ payloadKeyCount }} rows available</p>
                                <p class="text-xs text-muted-foreground">
                                    Open the table when you need exact values and types.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                class="mt-5 rounded-xl"
                                @click="openModal('debug')"
                            >
                                View debug table
                            </Button>
                        </div>

                        <div class="rounded-[24px] border border-dashed border-border/40 bg-background/60 p-5">
                            <p class="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                Live status
                            </p>
                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <Badge :variant="hasRecentData ? 'default' : 'outline'">
                                    {{ hasRecentData ? 'Receiving data' : 'Idle data' }}
                                </Badge>
                                <Badge variant="outline">
                                    {{ diagnostics.hardware.storage }}
                                </Badge>
                            </div>
                            <p class="mt-4 text-sm text-muted-foreground">
                                Latest sync:
                                {{ diagnostics.latest.updated_at ?? 'No timestamp available' }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Dialog
                :open="activeModal === 'connection'"
                @update:open="(value) => updateModalState('connection', value)"
            >
                <DialogContent class="max-h-[85vh] overflow-hidden sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Connection</DialogTitle>
                        <DialogDescription>
                            Broker, topics, and telemetry intervals for the active ESP32 device.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid max-h-[calc(85vh-10rem)] gap-3 overflow-y-auto pr-1 md:grid-cols-2">
                        <div class="rounded-[22px] border border-border/35 bg-background p-4">
                            <p class="text-[11px] text-muted-foreground">MQTT Broker</p>
                            <p class="mt-2 text-sm font-semibold tabular-nums">
                                {{ diagnostics.connection.mqtt_broker }}:{{ diagnostics.connection.mqtt_port }}
                            </p>
                        </div>
                        <div class="rounded-[22px] border border-border/35 bg-background p-4">
                            <p class="text-[11px] text-muted-foreground">Publish & Poll</p>
                            <p class="mt-2 text-sm font-semibold">
                                Publish {{ diagnostics.connection.publish_interval_seconds }}s
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Dashboard poll {{ diagnostics.connection.dashboard_poll_seconds }}s
                            </p>
                        </div>
                        <div class="rounded-[22px] border border-border/35 bg-background p-4">
                            <p class="text-[11px] text-muted-foreground">Command Topic</p>
                            <p class="mt-2 break-all text-sm font-semibold">
                                {{ diagnostics.connection.command_topic }}
                            </p>
                        </div>
                        <div class="rounded-[22px] border border-border/35 bg-background p-4">
                            <p class="text-[11px] text-muted-foreground">Telemetry Topic</p>
                            <p class="mt-2 break-all text-sm font-semibold">
                                {{ diagnostics.connection.telemetry_topic }}
                            </p>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="activeModal === 'hardware'"
                @update:open="(value) => updateModalState('hardware', value)"
            >
                <DialogContent class="max-h-[85vh] overflow-hidden sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Hardware Specifications</DialogTitle>
                        <DialogDescription>
                            Board identity, firmware build, relay capabilities, and ingest target.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid max-h-[calc(85vh-10rem)] gap-3 overflow-y-auto pr-1 md:grid-cols-2">
                        <div class="rounded-[22px] border border-border/35 bg-background p-4">
                            <p class="text-[11px] text-muted-foreground">Device Type</p>
                            <p class="mt-2 text-sm font-semibold">
                                {{ diagnostics.hardware.device_type }}
                            </p>
                        </div>
                        <div class="rounded-[22px] border border-border/35 bg-background p-4">
                            <p class="text-[11px] text-muted-foreground">Firmware</p>
                            <p class="mt-2 text-sm font-semibold">
                                {{ diagnostics.hardware.firmware_version }}
                            </p>
                        </div>
                        <div class="rounded-[22px] border border-border/35 bg-background p-4">
                            <p class="text-[11px] text-muted-foreground">Relay Count</p>
                            <p class="mt-2 text-sm font-semibold">
                                {{ diagnostics.hardware.relay_count }}
                            </p>
                        </div>
                        <div class="rounded-[22px] border border-border/35 bg-background p-4">
                            <p class="text-[11px] text-muted-foreground">Storage</p>
                            <p class="mt-2 text-sm font-semibold">
                                {{ diagnostics.hardware.storage }}
                            </p>
                        </div>
                        <div class="rounded-[22px] border border-border/35 bg-background p-4 md:col-span-2">
                            <p class="text-[11px] text-muted-foreground">Ingest Endpoint</p>
                            <p class="mt-2 break-all text-sm font-semibold">
                                {{ diagnostics.hardware.ingest_endpoint }}
                            </p>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="activeModal === 'pinout'"
                @update:open="(value) => updateModalState('pinout', value)"
            >
                <DialogContent class="max-h-[85vh] overflow-hidden sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Sensor Pinout</DialogTitle>
                        <DialogDescription>
                            Full ESP32 pin mapping for the analog sensors and relay outputs.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="max-h-[calc(85vh-10rem)] space-y-2 overflow-y-auto pr-1">
                        <div
                            v-for="pin in diagnostics.pinout"
                            :key="`${pin.name}-${pin.pin}`"
                            class="flex items-center justify-between gap-4 rounded-[22px] border border-border/35 bg-background px-4 py-3 text-sm"
                        >
                            <div>
                                <p class="font-medium">{{ pin.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ pin.type }}</p>
                            </div>
                            <span class="rounded-xl border border-border/40 px-3 py-1.5 text-xs font-medium">
                                {{ pin.pin }}
                            </span>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="activeModal === 'payload'"
                @update:open="(value) => updateModalState('payload', value)"
            >
                <DialogContent class="max-h-[85vh] overflow-hidden sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>JSON Payload</DialogTitle>
                        <DialogDescription>
                            Latest raw device snapshot together with MQTT topics and key preview.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="max-h-[calc(85vh-10rem)] space-y-4 overflow-y-auto pr-1">
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="rounded-[22px] border border-border/35 bg-background p-4">
                                <p class="text-[11px] text-muted-foreground">Data Topic</p>
                                <p class="mt-2 break-all font-mono text-[11px] text-foreground/80">
                                    {{ diagnostics.connection.telemetry_topic }}
                                </p>
                            </div>
                            <div class="rounded-[22px] border border-border/35 bg-background p-4">
                                <p class="text-[11px] text-muted-foreground">Command Topic</p>
                                <p class="mt-2 break-all font-mono text-[11px] text-foreground/80">
                                    {{ diagnostics.connection.command_topic }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="payloadPreviewEntries.length"
                            class="grid gap-2 rounded-[22px] border border-border/35 bg-background p-4"
                        >
                            <div
                                v-for="[key, value] in payloadPreviewEntries"
                                :key="key"
                                class="flex items-start justify-between gap-4 text-xs"
                            >
                                <span class="font-mono text-foreground/75">{{ key }}</span>
                                <span class="max-w-[65%] break-all text-right text-muted-foreground">
                                    {{ formatValue(value) }}
                                </span>
                            </div>
                        </div>

                        <pre class="max-h-96 overflow-auto rounded-[22px] border border-border/35 bg-background p-4 text-[11px] leading-relaxed text-foreground/70"><code>{{ rawJson }}</code></pre>
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="activeModal === 'debug'"
                @update:open="(value) => updateModalState('debug', value)"
            >
                <DialogContent class="max-h-[85vh] overflow-hidden sm:max-w-5xl">
                    <DialogHeader>
                        <DialogTitle>Debug State Table</DialogTitle>
                        <DialogDescription>
                            Typed key-by-key state table for the latest stored payload.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="max-h-[calc(85vh-10rem)] overflow-auto rounded-[22px] border border-border/35 bg-background">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-background">
                                <tr class="border-b border-border/20 text-left text-xs text-muted-foreground">
                                    <th class="px-4 py-3.5 font-medium">Key</th>
                                    <th class="px-4 py-3.5 font-medium">Value</th>
                                    <th class="px-4 py-3.5 font-medium">Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="[key, value] in latestEntries"
                                    :key="key"
                                    class="border-b border-border/15 last:border-0"
                                >
                                    <td class="px-4 py-3 font-mono text-xs font-medium">
                                        {{ key }}
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-muted-foreground break-all">
                                        {{ formatValue(value) }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-muted-foreground">
                                        {{ formatType(value) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </SettingsLayout>
</template>
