<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { buttonVariants } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationFirst,
  PaginationItem,
  PaginationLast,
  PaginationNext,
  PaginationPrevious,
} from '@/components/ui/pagination'
import { DatePicker } from '@/components/ui/date-picker'
import { cn } from '@/lib/utils'

type WarningCounters = {
  high?: number
  overload?: number
}

type DayWindowItem = {
  date: string
  day_short: string
  total: number
  is_today?: boolean
}

type DaySelectorMeta = {
  anchor_date: string
  min_date: string
  max_date: string
  window_start: string
  window_end: string
}

type SocketStat = {
  name: string
  energy_kwh: number
  percentage: number
  avg_power_w: number
  peak_power_w: number
  active_minutes: number
}

type IntervalStat = {
  start: string
  end: string
  energy_kwh: number
  duration_minutes: number
  avg_power_w: number
}

type HourlyStat = {
  hour: string
  energy_kwh: number
  avg_power_w: number
  peak_power_w: number
  warnings?: WarningCounters
}

type SelectedDay = {
  date: string
  total_kwh: number
  avg_voltage: number
  from_time?: string
  to_time?: string
  warnings?: WarningCounters
  socket_stats?: SocketStat[]
  intervals?: IntervalStat[]
  hourly?: HourlyStat[]
}

type HistoryPageProps = {
  latest: Record<string, unknown>
  dayWindow: DayWindowItem[]
  daySelector: DaySelectorMeta
  selectedDate: string
  selectedDay: SelectedDay
  weeklyTotal: number
  averageDay: number
  activeHours: number
  totalWarnings: number
  topHour?: { hour?: string; energy_kwh?: number } | null
  topSocket?: { name?: string; energy_kwh?: number; percentage?: number } | null
  peakDay?: { date?: string; total?: number } | null
  lastSeen: string
  isOnline: boolean
  historyBaseUrl: string
}

const props = defineProps<HistoryPageProps>()

const PER_PAGE = 4
const hourlyPage = ref(1)
const pickerDate = ref<string | undefined>(props.daySelector?.anchor_date ?? props.daySelector?.window_end ?? undefined)

const liveLastSeen = ref(props.lastSeen)
const liveOnline = ref(props.isOnline)

const dayWindowItems = computed(() => props.dayWindow ?? [])
const selectedWarnings = computed<WarningCounters>(() => props.selectedDay?.warnings ?? { high: 0, overload: 0 })
const socketStats = computed<SocketStat[]>(() => props.selectedDay?.socket_stats ?? [])
const intervals = computed<IntervalStat[]>(() => props.selectedDay?.intervals ?? [])
const hourlyLoad = computed<HourlyStat[]>(() => props.selectedDay?.hourly ?? [])
const topIntervals = computed(() => intervals.value.slice(0, 4))
const maxSocketEnergy = computed(() => Math.max(0.001, ...socketStats.value.map((item) => number(item.energy_kwh))))

const hourlyTotalPages = computed(() => Math.max(1, Math.ceil(hourlyLoad.value.length / PER_PAGE)))
const pagedHourly = computed(() => {
  const start = (hourlyPage.value - 1) * PER_PAGE
  return hourlyLoad.value.slice(start, start + PER_PAGE)
})
const pagedStartIndex = computed(() => (hourlyLoad.value.length ? (hourlyPage.value - 1) * PER_PAGE + 1 : 0))
const pagedEndIndex = computed(() => Math.min(hourlyPage.value * PER_PAGE, hourlyLoad.value.length))

watch(hourlyLoad, () => {
  hourlyPage.value = 1
})

watch(hourlyTotalPages, (maxPages) => {
  if (hourlyPage.value > maxPages) {
    hourlyPage.value = maxPages
  }
})

function number(value: unknown): number {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : 0
}

function fmt(value: unknown, digits = 1): string {
  return number(value).toFixed(digits)
}

function dayHref(date: string): string {
  const url = new URL(props.historyBaseUrl, window.location.origin)
  url.searchParams.set('date', date)
  url.searchParams.set('anchor_date', props.daySelector.anchor_date)
  return `${url.pathname}?${url.searchParams.toString()}`
}

function dayButtonClass(day: DayWindowItem): string {
  const isActive = day.date === props.selectedDate

  return cn(
    buttonVariants({ variant: isActive ? 'default' : 'outline', size: 'default' }),
    'h-auto w-full flex-col items-start gap-1 rounded-xl px-3 py-2.5 text-left',
    !isActive && 'text-foreground',
  )
}

function applyAnchorDate(selectedDate?: string): void {
  const date = selectedDate ?? pickerDate.value
  if (!date) return

  const url = new URL(props.historyBaseUrl, window.location.origin)
  url.searchParams.set('anchor_date', date)
  url.searchParams.set('date', date)
  window.location.href = `${url.pathname}?${url.searchParams.toString()}`
}

function socketBarWidth(energyKwh: number): number {
  return Math.max(8, (number(energyKwh) / maxSocketEnergy.value) * 100)
}

function lastSeenLabel(updatedAt: string | null): string {
  if (!updatedAt) return 'never'
  const timestamp = Date.parse(updatedAt)
  if (!Number.isFinite(timestamp)) return 'unknown'

  const diffSeconds = Math.max(0, Math.floor((Date.now() - timestamp) / 1000))
  if (diffSeconds < 5) return 'just now'
  if (diffSeconds < 60) return `${diffSeconds} sec ago`
  if (diffSeconds < 3600) return `${Math.floor(diffSeconds / 60)} min ago`
  return `${Math.floor(diffSeconds / 3600)} h ago`
}

function isDeviceOnline(updatedAt: string | null): boolean {
  if (!updatedAt) return false
  const timestamp = Date.parse(updatedAt)
  if (!Number.isFinite(timestamp)) return false

  return (Date.now() - timestamp) <= 5 * 60 * 1000
}

function hourlyState(hour: HourlyStat): 'overload' | 'high' | 'normal' {
  if ((hour.warnings?.overload ?? 0) > 0) return 'overload'
  if ((hour.warnings?.high ?? 0) > 0) return 'high'
  return 'normal'
}

const liveHandler = (event: Event) => {
  const detail = (event as CustomEvent<Record<string, unknown>>).detail ?? {}
  const updatedAt = typeof detail.updated_at === 'string' ? detail.updated_at : null

  liveOnline.value = isDeviceOnline(updatedAt)
  liveLastSeen.value = lastSeenLabel(updatedAt)
}

onMounted(() => {
  window.addEventListener('pulsenode:latest', liveHandler)
})

onUnmounted(() => {
  window.removeEventListener('pulsenode:latest', liveHandler)
})
</script>

<template>
  <div class="mx-auto max-w-[1360px] space-y-5 lg:space-y-6">
    <Card class="relative overflow-hidden gap-0 rounded-3xl border-border/30 py-0 shadow-none">
      <div class="pointer-events-none absolute inset-0 bg-linear-to-r from-primary/5 via-transparent to-transparent" />

      <CardHeader class="relative p-5 pb-0 sm:p-6 sm:pb-0">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <Badge
              variant="outline"
              :class="liveOnline ? 'border-emerald-500/40 text-emerald-300' : 'border-red-500/40 text-red-300'"
            >
              <span
                class="h-2 w-2 rounded-full"
                :class="liveOnline ? 'bg-emerald-400' : 'bg-red-400'"
              />
              {{ liveOnline ? 'Online' : 'Offline' }}
            </Badge>
            <Badge variant="secondary">
              History
            </Badge>
          </div>
          <p class="text-xs text-muted-foreground">
            Last sync:
            <span class="font-medium text-foreground">{{ liveLastSeen }}</span>
          </p>
        </div>
        <CardTitle class="text-2xl leading-tight font-bold tracking-tight">
          Home Energy Overview
        </CardTitle>
        <CardDescription class="mt-0.5 text-xs">
          {{ props.selectedDay.date }}
        </CardDescription>
      </CardHeader>

      <CardContent class="relative p-5 pt-5 sm:p-6 sm:pt-5">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
          <div class="rounded-2xl border border-border/40 bg-background p-4">
            <p class="text-xs text-muted-foreground">
              Day total
            </p>
            <p class="mt-1 text-2xl font-semibold leading-none tabular-nums">
              {{ fmt(props.selectedDay.total_kwh, 4) }}
              <span class="text-sm font-medium text-muted-foreground">kWh</span>
            </p>
          </div>
          <div class="rounded-2xl border border-border/40 bg-background p-4">
            <p class="text-xs text-muted-foreground">
              Week total
            </p>
            <p class="mt-1 text-2xl font-semibold leading-none tabular-nums">
              {{ fmt(props.weeklyTotal, 4) }}
              <span class="text-sm font-medium text-muted-foreground">kWh</span>
            </p>
          </div>
          <div class="rounded-2xl border border-border/40 bg-background p-4">
            <p class="text-xs text-muted-foreground">
              Avg day
            </p>
            <p class="mt-1 text-2xl font-semibold leading-none tabular-nums">
              {{ fmt(props.averageDay, 4) }}
            </p>
          </div>
          <div class="rounded-2xl border border-border/40 bg-background p-4">
            <p class="text-xs text-muted-foreground">
              Peak day
            </p>
            <p class="mt-1 text-2xl font-semibold leading-none tabular-nums">
              {{ fmt(props.peakDay?.total ?? 0, 4) }}
            </p>
          </div>
          <div class="rounded-2xl border border-border/40 bg-background p-4">
            <p class="text-xs text-muted-foreground">
              Most active hour
            </p>
            <p class="mt-1 text-2xl font-semibold leading-none tabular-nums">
              {{ props.topHour?.hour ?? '--:--' }}
            </p>
          </div>
          <div class="rounded-2xl border border-border/40 bg-background p-4">
            <p class="text-xs text-muted-foreground">
              Warnings
            </p>
            <p class="mt-1 text-2xl font-semibold leading-none tabular-nums">
              {{ props.totalWarnings }}
            </p>
          </div>
        </div>
      </CardContent>
    </Card>

    <Card class="gap-0 rounded-3xl border-border/30 py-0 shadow-none">
      <CardHeader class="p-5 pb-0 sm:p-6 sm:pb-0">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <CardTitle class="text-base">
            Day selector
          </CardTitle>
          <p class="text-xs text-muted-foreground">
            Range: {{ props.daySelector.window_start }} -> {{ props.daySelector.window_end }}
          </p>
        </div>
      </CardHeader>
      <CardContent class="p-5 pt-4 sm:p-6 sm:pt-4">
        <div class="mb-4 max-w-xs">
          <DatePicker
            v-model="pickerDate"
            :min="props.daySelector.min_date"
            :max="props.daySelector.max_date"
            placeholder="Selecteaza anchor day"
            class="h-10"
            @change="applyAnchorDate"
          />
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-7">
          <a
            v-for="day in dayWindowItems"
            :key="day.date"
            :href="dayHref(day.date)"
            :class="dayButtonClass(day)"
          >
            <span class="text-[11px] uppercase tracking-[0.14em] opacity-85">
              {{ day.day_short }}
            </span>
            <span class="text-sm font-semibold tabular-nums">
              {{ fmt(day.total, 4) }} kWh
            </span>
            <span class="text-[11px] opacity-70">
              {{ day.is_today ? 'Today' : day.date }}
            </span>
          </a>
        </div>
      </CardContent>
    </Card>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.62fr)_minmax(320px,0.9fr)]">
      <div class="space-y-5">
        <Card class="gap-0 rounded-3xl border-border/30 py-0 shadow-none">
          <CardHeader class="p-5 pb-0 sm:p-6 sm:pb-0">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <CardTitle class="text-base">
                Hourly load map
              </CardTitle>
              <p class="text-xs text-muted-foreground">
                {{ pagedStartIndex }}-{{ pagedEndIndex }} / {{ hourlyLoad.length }}
              </p>
            </div>
          </CardHeader>
          <CardContent class="space-y-4 p-5 pt-4 sm:p-6 sm:pt-4">
            <div class="grid gap-3 sm:grid-cols-2">
              <div
                v-for="hour in pagedHourly"
                :key="hour.hour"
                class="rounded-2xl border border-border/40 bg-background p-4"
              >
                <div class="flex items-center justify-between gap-2">
                  <p class="font-semibold tabular-nums">
                    {{ hour.hour }}
                  </p>
                  <Badge
                    variant="outline"
                    :class="hourlyState(hour) === 'overload'
                      ? 'border-red-500/40 text-red-300'
                      : hourlyState(hour) === 'high'
                        ? 'border-amber-500/40 text-amber-300'
                        : 'border-border text-muted-foreground'"
                  >
                    {{ hourlyState(hour) }}
                  </Badge>
                </div>
                <p class="mt-1.5 text-xl font-semibold leading-none tabular-nums">
                  {{ fmt(hour.energy_kwh, 4) }} <span class="text-sm font-medium text-muted-foreground">kWh</span>
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                  Avg {{ fmt(hour.avg_power_w, 1) }} W · Peak {{ fmt(hour.peak_power_w, 1) }} W
                </p>
              </div>
            </div>

            <Pagination
              v-if="hourlyLoad.length > PER_PAGE"
              v-model:page="hourlyPage"
              :items-per-page="PER_PAGE"
              :total="hourlyLoad.length"
              :sibling-count="1"
              show-edges
            >
              <PaginationContent v-slot="{ items }" class="justify-center">
                <PaginationFirst />
                <PaginationPrevious />

                <template
                  v-for="(item, index) in items"
                  :key="`hourly-page-${index}`"
                >
                  <PaginationItem
                    v-if="item.type === 'page'"
                    :value="item.value"
                    :is-active="item.value === hourlyPage"
                  >
                    {{ item.value }}
                  </PaginationItem>
                  <PaginationEllipsis
                    v-else
                    :index="index"
                  />
                </template>

                <PaginationNext />
                <PaginationLast />
              </PaginationContent>
            </Pagination>
          </CardContent>
        </Card>

        <Card class="gap-0 rounded-3xl border-border/30 py-0 shadow-none">
          <CardHeader class="p-5 pb-0 sm:p-6 sm:pb-0">
            <CardTitle class="text-base">
              Socket contribution
            </CardTitle>
          </CardHeader>
          <CardContent class="grid gap-3 p-5 pt-4 sm:grid-cols-2 sm:p-6 sm:pt-4 2xl:grid-cols-3">
            <div
              v-for="socket in socketStats"
              :key="socket.name"
              class="rounded-2xl border border-border/40 bg-background p-4"
            >
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="text-sm font-semibold">
                    {{ socket.name }}
                  </p>
                  <p class="text-xs text-muted-foreground">
                    Avg {{ fmt(socket.avg_power_w, 1) }} W · Peak {{ fmt(socket.peak_power_w, 1) }} W
                  </p>
                </div>
                <p class="text-sm font-semibold tabular-nums">
                  {{ fmt(socket.energy_kwh, 4) }} kWh
                </p>
              </div>
              <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                <div
                  class="h-full rounded-full bg-primary"
                  :style="{ width: `${socketBarWidth(socket.energy_kwh)}%` }"
                />
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card class="gap-0 rounded-3xl border-border/30 py-0 shadow-none">
        <CardHeader class="p-5 pb-0 sm:p-6 sm:pb-0">
          <CardTitle class="text-base">
            Selected day
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-4 p-5 pt-4 sm:p-6 sm:pt-4">
          <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
            <div class="rounded-2xl border border-border/40 bg-background p-4">
              <p class="text-xs text-muted-foreground">
                Average voltage
              </p>
              <p class="mt-1 text-lg font-semibold leading-none tabular-nums">
                {{ fmt(props.selectedDay.avg_voltage, 1) }} V
              </p>
            </div>
            <div class="rounded-2xl border border-border/40 bg-background p-4">
              <p class="text-xs text-muted-foreground">
                Active hours
              </p>
              <p class="mt-1 text-lg font-semibold leading-none tabular-nums">
                {{ props.activeHours }}
              </p>
            </div>
            <div class="rounded-2xl border border-border/40 bg-background p-4">
              <p class="text-xs text-muted-foreground">
                High load
              </p>
              <p class="mt-1 text-lg font-semibold leading-none tabular-nums">
                {{ selectedWarnings.high ?? 0 }}
              </p>
            </div>
            <div class="rounded-2xl border border-border/40 bg-background p-4">
              <p class="text-xs text-muted-foreground">
                Overload
              </p>
              <p class="mt-1 text-lg font-semibold leading-none tabular-nums">
                {{ selectedWarnings.overload ?? 0 }}
              </p>
            </div>
          </div>

          <div class="rounded-2xl border border-border/40 bg-background p-4">
            <p class="text-xs text-muted-foreground">
              Dominant socket
            </p>
            <p class="mt-1 text-sm font-semibold">
              {{ props.topSocket?.name ?? 'Unavailable' }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
              {{ fmt(props.topSocket?.energy_kwh ?? 0, 4) }} kWh · {{ props.topSocket?.percentage ?? 0 }}%
            </p>
          </div>

          <div class="space-y-2">
            <p class="text-xs font-medium text-muted-foreground">
              Peak intervals
            </p>
            <div
              v-for="interval in topIntervals.slice(0, 3)"
              :key="`${interval.start}-${interval.end}`"
              class="rounded-xl border border-border/40 bg-background p-3"
            >
              <div class="flex items-center justify-between gap-2">
                <p class="text-sm font-semibold tabular-nums">
                  {{ interval.start }} - {{ interval.end }}
                </p>
                <p class="text-sm font-semibold tabular-nums">
                  {{ fmt(interval.energy_kwh, 4) }} kWh
                </p>
              </div>
            </div>
            <p
              v-if="!topIntervals.length"
              class="rounded-xl border border-dashed border-border/50 bg-background p-3 text-xs text-muted-foreground"
            >
              No peak intervals for this day.
            </p>
          </div>
        </CardContent>
      </Card>
    </div>

  </div>
</template>
