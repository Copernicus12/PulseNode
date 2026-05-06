<script setup lang="ts">
import { ChevronLeft } from 'lucide-vue-next'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import Chart from 'primevue/chart'
import {
  CategoryScale,
  Chart as ChartJS,
  Filler,
  Legend,
  LineElement,
  LinearScale,
  PointElement,
  Tooltip,
} from 'chart.js'
import { Badge } from '@/components/ui/badge'
import { Button, buttonVariants } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { DatePicker } from '@/components/ui/date-picker'
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { cn } from '@/lib/utils'

ChartJS.register(
  CategoryScale,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip,
  Legend,
  Filler,
)

type WarningCounters = {
  high?: number
  overload?: number
}

type DayWindowItem = {
  date: string
  day_short: string
  total: number
  is_today?: boolean
  is_future?: boolean
  is_selectable?: boolean
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

type SecondStat = {
  second: string
  energy_kwh: number
  avg_power_w: number
  peak_power_w: number
  warnings?: WarningCounters
}

type MinuteStat = {
  minute: string
  energy_kwh: number
  avg_power_w: number
  peak_power_w: number
  warnings?: WarningCounters
  seconds?: SecondStat[]
}

type HourlyStat = {
  hour: string
  energy_kwh: number
  avg_power_w: number
  peak_power_w: number
  warnings?: WarningCounters
  minutes?: MinuteStat[]
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

type BillingCostLine = {
  energy_kwh: number
  subtotal: number
  tax_amount: number
  total_cost: number
}

type BillingSocketCost = BillingCostLine & {
  name: string
}

type BillingSummary = {
  profile_name?: string | null
  profile_label: string
  profile_source: 'saved_profile' | 'current_settings'
  currency: string
  price_per_kwh: number
  price_per_kwh_with_tax: number
  tax_percent: number
  day: BillingCostLine
  sockets: BillingSocketCost[]
}

type HistoryPageProps = {
  latest: Record<string, unknown>
  dayWindow: DayWindowItem[]
  daySelector: DaySelectorMeta
  selectedDate: string
  selectedDay: SelectedDay
  billingSummary: BillingSummary
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

type UpdateHistoryOptions = {
  mode?: 'push' | 'replace'
  silent?: boolean
  preservePicker?: boolean
}

const props = defineProps<HistoryPageProps>()
const historyState = ref<HistoryPageProps>({
  ...props,
  dayWindow: [...(props.dayWindow ?? [])],
  daySelector: { ...props.daySelector },
  selectedDay: { ...(props.selectedDay ?? {}) },
})

const detailOpen = ref(false)
const isLoading = ref(false)
const selectedHourKey = ref<string | null>(null)
const selectedMinuteKey = ref<string | null>(null)
const pickerDate = ref<string | undefined>(historyState.value.daySelector?.anchor_date ?? historyState.value.daySelector?.window_end ?? undefined)

const liveLastSeen = ref(historyLastSeenLabel(historyState.value))
const liveOnline = ref(historyState.value.isOnline)
const selectedDateIsToday = computed(() => (
  historyState.value.selectedDate === new Date().toISOString().slice(0, 10)
))

const dayWindowItems = computed(() => historyState.value.dayWindow ?? [])
const selectedWarnings = computed<WarningCounters>(() => historyState.value.selectedDay?.warnings ?? { high: 0, overload: 0 })
const socketStats = computed<SocketStat[]>(() => historyState.value.selectedDay?.socket_stats ?? [])
const intervals = computed<IntervalStat[]>(() => historyState.value.selectedDay?.intervals ?? [])
const hourlyLoad = computed<HourlyStat[]>(() => historyState.value.selectedDay?.hourly ?? [])
const currentBillingSummary = computed<BillingSummary>(() => historyState.value.billingSummary ?? {
  profile_label: 'Current settings',
  profile_source: 'current_settings',
  currency: 'RON',
  price_per_kwh: 0,
  price_per_kwh_with_tax: 0,
  tax_percent: 0,
  day: {
    energy_kwh: 0,
    subtotal: 0,
    tax_amount: 0,
    total_cost: 0,
  },
  sockets: [],
})
const socketBillingMap = computed<Record<string, BillingSocketCost>>(() => (
  Object.fromEntries(currentBillingSummary.value.sockets.map((socket) => [socket.name, socket]))
))
const topIntervals = computed(() => intervals.value.slice(0, 4))
const maxSocketEnergy = computed(() => Math.max(0.001, ...socketStats.value.map((item) => number(item.energy_kwh))))

const selectedHour = computed<HourlyStat | null>(() => hourlyLoad.value.find((item) => item.hour === selectedHourKey.value) ?? null)
const selectedHourMinutes = computed<MinuteStat[]>(() => selectedHour.value?.minutes ?? [])
const selectedMinute = computed<MinuteStat | null>(() => (
  selectedHourMinutes.value.find((item) => item.minute === selectedMinuteKey.value) ?? null
))
const selectedMinuteSeconds = computed<SecondStat[]>(() => selectedMinute.value?.seconds ?? [])
const themeRevision = ref(0)
const hourlyChartData = computed(() => {
  void themeRevision.value

  return buildMixedChartData(
    hourlyLoad.value,
    (item) => item.hour,
  )
})
const hourlyChartOptions = computed(() => {
  void themeRevision.value

  return buildChartOptions((index) => {
    const hour = hourlyLoad.value[index]
    if (hour) openHourDetails(hour)
  }, 'hour')
})
const minuteChartData = computed(() => {
  void themeRevision.value

  return buildMixedChartData(
    selectedHourMinutes.value,
    (item) => item.minute.slice(-2),
  )
})
const minuteChartOptions = computed(() => {
  void themeRevision.value

  return buildChartOptions((index) => {
    const minute = selectedHourMinutes.value[index]
    if (minute) openMinuteDetails(minute)
  }, 'minute', {
    pointRadius: 0,
    pointHoverRadius: 0,
    pointHitRadius: 14,
    animationDuration: 0,
  })
})
const secondChartData = computed(() => {
  void themeRevision.value

  return buildMixedChartData(
    selectedMinuteSeconds.value,
    (item) => item.second.slice(-2),
  )
})
const secondChartOptions = computed(() => {
  void themeRevision.value

  return buildChartOptions(() => {}, 'second', {
    pointRadius: 0,
    pointHoverRadius: 0,
    pointHitRadius: 0,
    animationDuration: 0,
  })
})

watch(selectedHour, () => {
  selectedMinuteKey.value = null
})

watch(() => historyState.value.selectedDate, () => {
  selectedHourKey.value = null
  selectedMinuteKey.value = null
  detailOpen.value = false
})

watch(hourlyLoad, (hours) => {
  if (!hours.length) {
    selectedHourKey.value = null
    return
  }

  if (selectedHourKey.value && hours.some((hour) => hour.hour === selectedHourKey.value)) {
    return
  }

  selectedHourKey.value = historyState.value.topHour?.hour ?? hours[0].hour
}, { immediate: true })

watch(detailOpen, (isOpen) => {
  if (!isOpen) {
    selectedMinuteKey.value = null
  }
})

let themeObserver: MutationObserver | null = null

let liveRefreshTimeout: number | null = null
let liveRefreshInterval: number | null = null
let historyRequestInFlight = false

function number(value: unknown): number {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : 0
}

function fmt(value: unknown, digits = 1): string {
  return number(value).toFixed(digits)
}

function fmtPower(value: unknown, digits = 1): string {
  return Math.max(0, number(value)).toFixed(digits)
}

function fmtEnergy(value: unknown): string {
  const kwh = number(value)
  if (kwh >= 0.01) {
    return `${kwh.toFixed(4)} kWh`
  }

  return `${(kwh * 1000).toFixed(2)} Wh`
}

function fmtCurrency(value: unknown, currency = currentBillingSummary.value.currency): string {
  const amount = number(value)

  try {
    return new Intl.NumberFormat('ro-RO', {
      style: 'currency',
      currency,
      minimumFractionDigits: amount >= 1 ? 2 : 4,
      maximumFractionDigits: amount >= 1 ? 2 : 4,
    }).format(amount)
  } catch {
    return `${amount.toFixed(amount >= 1 ? 2 : 4)} ${currency}`
  }
}

function dayButtonClass(day: DayWindowItem): string {
  const isActive = day.date === historyState.value.selectedDate
  const isSelectable = day.is_selectable !== false

  return cn(
    buttonVariants({ variant: isActive && isSelectable ? 'default' : 'outline', size: 'default' }),
    'h-auto w-full select-none flex-col items-start gap-1 rounded-xl px-3 py-2.5 text-left outline-none ring-0 focus:outline-none focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary/25 focus-visible:ring-offset-0',
    !isSelectable
      ? 'cursor-not-allowed border-border/30 text-muted-foreground/45 opacity-55 hover:border-border/30 hover:bg-background hover:text-muted-foreground/45'
      : isActive
      ? 'hover:bg-primary hover:text-primary-foreground'
      : 'text-foreground hover:border-border/50 hover:bg-background hover:text-foreground',
  )
}

function detailUrl(date: string, anchorDate: string): string {
  const url = new URL(historyState.value.historyBaseUrl, window.location.origin)
  url.searchParams.set('date', date)
  url.searchParams.set('anchor_date', anchorDate)

  return `${url.pathname}?${url.searchParams.toString()}`
}

async function updateHistory(
  date: string,
  anchorDate: string,
  options: UpdateHistoryOptions = {},
): Promise<void> {
  const {
    mode = 'push',
    silent = false,
    preservePicker = false,
  } = options

  if (historyRequestInFlight) return
  if (isLoading.value && !silent) return

  const url = detailUrl(date, anchorDate)

  if (!silent) {
    isLoading.value = true
  }

  historyRequestInFlight = true

  try {
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })

    if (!response.ok) {
      throw new Error(`Failed to load history state: ${response.status}`)
    }

    const payload = await response.json() as HistoryPageProps
    historyState.value = payload

    if (!preservePicker) {
      pickerDate.value = payload.daySelector?.anchor_date ?? payload.daySelector?.window_end ?? pickerDate.value
    }

    liveLastSeen.value = historyLastSeenLabel(payload)
    liveOnline.value = payload.isOnline

    if (mode === 'replace') {
      window.history.replaceState(null, '', url)
    } else {
      window.history.pushState(null, '', url)
    }
  } catch (error) {
    console.error('Unable to update history view', error)
  } finally {
    historyRequestInFlight = false

    if (!silent) {
      isLoading.value = false
    }
  }
}

function applyAnchorDate(selectedDate?: string): void {
  const date = selectedDate ?? pickerDate.value
  if (!date) return

  void updateHistory(date, date)
}

function selectDay(day: DayWindowItem): void {
  if (day.is_selectable === false) return

  void updateHistory(day.date, day.date)
}

function socketBarWidth(energyKwh: number): number {
  return Math.max(8, (number(energyKwh) / maxSocketEnergy.value) * 100)
}

function getChartTokens(): {
  mutedText: string
  border: string
  chart1: string
  chart2: string
} {
  if (typeof document === 'undefined') {
    return {
      mutedText: 'hsl(0 0% 45%)',
      border: 'hsl(0 0% 92%)',
      chart1: 'hsl(12 76% 61%)',
      chart2: 'hsl(173 58% 39%)',
    }
  }

  const styles = getComputedStyle(document.documentElement)

  return {
    mutedText: styles.getPropertyValue('--muted-foreground').trim() || 'hsl(0 0% 45%)',
    border: styles.getPropertyValue('--border').trim() || 'hsl(0 0% 92%)',
    chart1: styles.getPropertyValue('--chart-1').trim() || 'hsl(12 76% 61%)',
    chart2: styles.getPropertyValue('--chart-2').trim() || 'hsl(173 58% 39%)',
  }
}

function buildMixedChartData<T extends { energy_kwh: number; avg_power_w: number; warnings?: WarningCounters }>(
  items: T[],
  labelGetter: (item: T, index: number) => string,
) {
  const tokens = getChartTokens()

  return {
    labels: items.map(labelGetter),
    datasets: [
      {
        type: 'line',
        label: 'Energy (kWh)',
        data: items.map((item) => number(item.energy_kwh)),
        borderColor: tokens.chart2,
        backgroundColor: tokens.chart2,
        pointBackgroundColor: tokens.chart2,
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 3,
        tension: 0.35,
        fill: false,
        yAxisID: 'y',
      },
      {
        type: 'line',
        label: 'Average power (W)',
        data: items.map((item) => number(item.avg_power_w)),
        borderColor: tokens.chart1,
        backgroundColor: tokens.chart1,
        pointBackgroundColor: tokens.chart1,
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 3,
        tension: 0.35,
        fill: false,
        yAxisID: 'y1',
      },
    ],
  }
}

type ChartHoverConfig = {
  pointRadius?: number
  pointHoverRadius?: number
  pointHitRadius?: number
  animationDuration?: number
}

function buildChartOptions(
  onSelect: (index: number) => void,
  kind: 'hour' | 'minute' | 'second',
  hoverConfig: ChartHoverConfig | null = null,
) {
  const tokens = getChartTokens()
  const interactive = kind !== 'second'
  const pointRadius = hoverConfig?.pointRadius ?? 3
  const pointHoverRadius = hoverConfig?.pointHoverRadius ?? 3
  const pointHitRadius = hoverConfig?.pointHitRadius ?? 8
  const animationDuration = hoverConfig?.animationDuration ?? 120

  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: animationDuration > 0
      ? {
          duration: animationDuration,
        }
      : false,
    interaction: {
      mode: 'index',
      intersect: false,
    },
    hover: {
      mode: 'index',
      intersect: false,
    },
    elements: {
      point: {
        radius: pointRadius,
        hoverRadius: pointHoverRadius,
        hitRadius: pointHitRadius,
        borderWidth: 2,
        hoverBorderWidth: 2,
      },
    },
    onClick: (_event: unknown, elements: Array<{ index: number }>) => {
      const index = elements?.[0]?.index
      if (typeof index === 'number') {
        onSelect(index)
      }
    },
    plugins: {
      legend: {
        display: false,
      },
      tooltip: {
        enabled: true,
        backgroundColor: 'rgba(15, 23, 42, 0.96)',
        borderColor: 'rgba(148, 163, 184, 0.22)',
        borderWidth: 1,
        titleColor: '#ffffff',
        bodyColor: '#e2e8f0',
        padding: 12,
        cornerRadius: 12,
        displayColors: true,
        callbacks: {
          label(context: { dataset: { label?: string }; parsed: { y?: number } }) {
            const value = Number(context.parsed.y ?? 0)
            if (context.dataset.label === 'Average power (W)') {
              return ` ${context.dataset.label}: ${fmtPower(value, 1)} W`
            }

            return ` ${context.dataset.label}: ${fmt(value, 4)} kWh`
          },
          title(context: Array<{ label?: string }>) {
            if (kind === 'hour') return `Hour ${context[0]?.label ?? ''}`
            if (kind === 'minute') return `Minute ${context[0]?.label ?? ''}`
            return `Second ${context[0]?.label ?? ''}`
          },
        },
      },
    },
    scales: {
      x: {
        ticks: {
          color: tokens.mutedText,
          autoSkip: true,
          maxRotation: 0,
        },
        grid: {
          display: false,
        },
        border: {
          color: tokens.border,
        },
      },
      y: {
        beginAtZero: true,
        position: 'left',
        ticks: {
          color: tokens.mutedText,
          callback: (value: string | number) => `${value} kWh`,
        },
        grid: {
          color: tokens.border,
        },
        border: {
          color: tokens.border,
        },
      },
      y1: {
        beginAtZero: true,
        position: 'right',
        ticks: {
          color: tokens.mutedText,
          callback: (value: string | number) => `${value} W`,
        },
        grid: {
          drawOnChartArea: false,
          color: tokens.border,
        },
        border: {
          color: tokens.border,
        },
      },
    },
  }
}

function lastSeenLabel(updatedAt: string | null): string {
  if (!updatedAt) return 'never'
  const timestamp = Date.parse(updatedAt)
  if (!Number.isFinite(timestamp)) return 'unknown'

  const diffSeconds = Math.max(0, Math.floor((Date.now() - timestamp) / 1000))
  if (diffSeconds < 5) return 'just now'
  if (diffSeconds < 60) return `${diffSeconds} sec ago`
  if (diffSeconds < 3600) return `${Math.floor(diffSeconds / 60)} min ago`
  if (diffSeconds < 86400) return `${Math.floor(diffSeconds / 3600)} h ago`
  if (diffSeconds < 604800) return `${Math.floor(diffSeconds / 86400)} d ago`
  if (diffSeconds < 2629800) return pluralAge(Math.floor(diffSeconds / 604800), 'week')
  if (diffSeconds < 31557600) return pluralAge(Math.floor(diffSeconds / 2629800), 'month')
  return pluralAge(Math.floor(diffSeconds / 31557600), 'year')
}

function pluralAge(value: number, unit: string): string {
  return `${value} ${unit}${value === 1 ? '' : 's'} ago`
}

function latestUpdatedAt(latest: Record<string, unknown> | null | undefined): string | null {
  const updatedAt = latest?.updated_at

  return typeof updatedAt === 'string' && updatedAt.length > 0 ? updatedAt : null
}

function historyLastSeenLabel(state: HistoryPageProps): string {
  const updatedAt = latestUpdatedAt(state.latest)

  return updatedAt ? lastSeenLabel(updatedAt) : state.lastSeen
}

function isDeviceOnline(updatedAt: string | null): boolean {
  if (!updatedAt) return false
  const timestamp = Date.parse(updatedAt)
  if (!Number.isFinite(timestamp)) return false

  return (Date.now() - timestamp) <= 5 * 60 * 1000
}

function loadState(item: { warnings?: WarningCounters }): 'overload' | 'high' | 'normal' {
  if ((item.warnings?.overload ?? 0) > 0) return 'overload'
  if ((item.warnings?.high ?? 0) > 0) return 'high'
  return 'normal'
}

function openHourDetails(hour: HourlyStat): void {
  selectedHourKey.value = hour.hour
  detailOpen.value = true
}

function openMinuteDetails(minute: MinuteStat): void {
  selectedMinuteKey.value = minute.minute
}

function goBackToMinutes(): void {
  selectedMinuteKey.value = null
}

function refreshTodayHistory(): void {
  if (!selectedDateIsToday.value || isLoading.value) return

  void updateHistory(
    historyState.value.selectedDate,
    historyState.value.daySelector.anchor_date,
    {
      mode: 'replace',
      silent: true,
      preservePicker: true,
    },
  )
}

function scheduleTodayHistoryRefresh(delay = 1500): void {
  if (!selectedDateIsToday.value) return

  if (liveRefreshTimeout !== null) {
    window.clearTimeout(liveRefreshTimeout)
  }

  liveRefreshTimeout = window.setTimeout(() => {
    liveRefreshTimeout = null
    refreshTodayHistory()
  }, delay)
}

const liveHandler = (event: Event) => {
  const detail = (event as CustomEvent<Record<string, unknown>>).detail ?? {}
  const updatedAt = typeof detail.updated_at === 'string' ? detail.updated_at : null

  liveOnline.value = isDeviceOnline(updatedAt)
  liveLastSeen.value = lastSeenLabel(updatedAt)
  scheduleTodayHistoryRefresh()
}

const popStateHandler = () => {
  const url = new URL(window.location.href)
  const anchorDate = url.searchParams.get('anchor_date') ?? historyState.value.daySelector.anchor_date
  const date = url.searchParams.get('date') ?? anchorDate

  void updateHistory(date, anchorDate, { mode: 'replace' })
}

onMounted(() => {
  window.addEventListener('pulsenode:latest', liveHandler)
  window.addEventListener('popstate', popStateHandler)
  liveRefreshInterval = window.setInterval(refreshTodayHistory, 30000)

  themeObserver = new MutationObserver(() => {
    themeRevision.value += 1
  })
  themeObserver.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class', 'style'],
  })
})

onUnmounted(() => {
  window.removeEventListener('pulsenode:latest', liveHandler)
  window.removeEventListener('popstate', popStateHandler)

  if (liveRefreshTimeout !== null) {
    window.clearTimeout(liveRefreshTimeout)
  }

  if (liveRefreshInterval !== null) {
    window.clearInterval(liveRefreshInterval)
  }

  themeObserver?.disconnect()
})
</script>

<template>
  <div class="mx-auto max-w-[1360px] space-y-5 lg:space-y-6">
    <Card id="history-overview-card" class="relative overflow-hidden gap-0 rounded-3xl border-border/30 py-0 shadow-none">
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
          {{ historyState.selectedDay.date }}
        </CardDescription>
      </CardHeader>

      <CardContent class="relative p-5 pt-5 sm:p-6 sm:pt-5">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-8">
          <div class="rounded-2xl border border-border/40 bg-background p-4">
            <p class="text-xs text-muted-foreground">
              Day total
            </p>
            <p class="mt-1 text-2xl font-semibold leading-none tabular-nums">
              {{ fmt(historyState.selectedDay.total_kwh, 4) }}
              <span class="text-sm font-medium text-muted-foreground">kWh</span>
            </p>
          </div>
          <div class="rounded-2xl border border-border/40 bg-background p-4">
            <p class="text-xs text-muted-foreground">
              Day cost
            </p>
            <p class="mt-1 text-2xl font-semibold leading-none tabular-nums">
              {{ fmtCurrency(currentBillingSummary.day.total_cost) }}
            </p>
          </div>
          <div class="rounded-2xl border border-border/40 bg-background p-4">
            <p class="text-xs text-muted-foreground">
              Active tariff
            </p>
            <p class="mt-1 text-lg font-semibold leading-none">
              {{ currentBillingSummary.profile_label }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
              {{ fmtCurrency(currentBillingSummary.price_per_kwh_with_tax) }}/kWh incl. VAT
            </p>
          </div>
          <div class="rounded-2xl border border-border/40 bg-background p-4">
            <p class="text-xs text-muted-foreground">
              Week total
            </p>
            <p class="mt-1 text-2xl font-semibold leading-none tabular-nums">
              {{ fmt(historyState.weeklyTotal, 4) }}
              <span class="text-sm font-medium text-muted-foreground">kWh</span>
            </p>
          </div>
          <div class="rounded-2xl border border-border/40 bg-background p-4">
            <p class="text-xs text-muted-foreground">
              Avg day
            </p>
            <p class="mt-1 text-2xl font-semibold leading-none tabular-nums">
              {{ fmt(historyState.averageDay, 4) }}
            </p>
          </div>
          <div class="rounded-2xl border border-border/40 bg-background p-4">
            <p class="text-xs text-muted-foreground">
              Peak day
            </p>
            <p class="mt-1 text-2xl font-semibold leading-none tabular-nums">
              {{ fmt(historyState.peakDay?.total ?? 0, 4) }}
            </p>
          </div>
          <div class="rounded-2xl border border-border/40 bg-background p-4">
            <p class="text-xs text-muted-foreground">
              Most active hour
            </p>
            <p class="mt-1 text-2xl font-semibold leading-none tabular-nums">
              {{ historyState.topHour?.hour ?? '--:--' }}
            </p>
          </div>
          <div class="rounded-2xl border border-border/40 bg-background p-4">
            <p class="text-xs text-muted-foreground">
              Warnings
            </p>
            <p class="mt-1 text-2xl font-semibold leading-none tabular-nums">
              {{ historyState.totalWarnings }}
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
            Range: {{ historyState.daySelector.window_start }} -> {{ historyState.daySelector.window_end }}
          </p>
        </div>
      </CardHeader>
      <CardContent class="p-5 pt-4 sm:p-6 sm:pt-4" :class="isLoading && 'pointer-events-none opacity-70'">
        <div class="mb-4 max-w-xs">
          <DatePicker
            v-model="pickerDate"
            :min="historyState.daySelector.min_date"
            :max="historyState.daySelector.max_date"
            placeholder="Select day"
            class="h-10"
            @change="applyAnchorDate"
          />
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-7">
          <button
            v-for="day in dayWindowItems"
            :key="day.date"
            type="button"
            :class="dayButtonClass(day)"
            :disabled="isLoading || day.is_selectable === false"
            @click="selectDay(day)"
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
          </button>
        </div>
      </CardContent>
    </Card>

    <div class="grid items-start gap-5 xl:items-stretch xl:grid-cols-[minmax(0,1.62fr)_minmax(320px,0.9fr)]">
      <div class="space-y-5">
        <Card id="history-hourly-map" class="gap-0 rounded-3xl border-border/30 py-0 shadow-none">
          <CardHeader class="p-5 pb-0 sm:p-6 sm:pb-0">
            <div class="flex flex-wrap items-end justify-between gap-3">
              <div>
                <p class="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                  Hourly load map
                </p>
                <CardTitle class="mt-1 text-base">
                  Energy trend across the day
                </CardTitle>
          </div>
            </div>
          </CardHeader>
          <CardContent class="p-5 pt-4 sm:p-6 sm:pt-4">
            <div class="rounded-[28px] border border-border/40 bg-background/95 p-4">
              <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-muted-foreground">
                  Click a bar to inspect minute and second details.
                </p>
                <p class="text-xs text-muted-foreground">
                  {{ hourlyLoad.length }} hourly samples
                </p>
              </div>

              <div
                v-if="hourlyLoad.length"
                class="overflow-x-auto rounded-[24px] bg-background p-4"
              >
                <div class="min-w-[980px]">
                  <Chart
                    type="line"
                    :data="hourlyChartData"
                    :options="hourlyChartOptions"
                    class="h-[22rem] w-full"
                  />
                </div>
              </div>

              <div
                v-else
                class="rounded-[24px] border border-dashed border-border/50 bg-background p-6 text-sm text-muted-foreground"
              >
                No hourly samples are available for this day.
              </div>
            </div>
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
                    Avg {{ fmtPower(socket.avg_power_w, 1) }} W · Peak {{ fmtPower(socket.peak_power_w, 1) }} W
                  </p>
                </div>
                <p class="text-sm font-semibold tabular-nums">
                  {{ fmt(socket.energy_kwh, 4) }} kWh
                </p>
              </div>
              <p class="mt-3 text-sm font-semibold tabular-nums text-primary">
                {{ fmtCurrency(socketBillingMap[socket.name]?.total_cost ?? 0) }}
              </p>
              <p class="mt-1 text-[11px] text-muted-foreground">
                Gross price for today's consumption
              </p>
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

       <Card class="gap-0 rounded-3xl border-border/30 py-0 shadow-none xl:self-start xl:flex xl:h-[978px] xl:min-h-0 xl:flex-col">        <CardHeader class="p-5 pb-0 sm:p-6 sm:pb-0">
          <CardTitle class="text-base">
            Selected day
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-4 p-5 pt-4 sm:p-6 sm:pt-4 xl:min-h-0 xl:flex-1 xl:overflow-y-auto xl:pr-4">
          <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
            <div class="rounded-2xl border border-border/40 bg-background p-4">
              <p class="text-xs text-muted-foreground">
                Average voltage
              </p>
              <p class="mt-1 text-lg font-semibold leading-none tabular-nums">
                {{ fmt(historyState.selectedDay.avg_voltage, 1) }} V
              </p>
            </div>
            <div class="rounded-2xl border border-border/40 bg-background p-4">
              <p class="text-xs text-muted-foreground">
                Active hours
              </p>
              <p class="mt-1 text-lg font-semibold leading-none tabular-nums">
                {{ historyState.activeHours }}
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
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-xs text-muted-foreground">
                  Billing profile
                </p>
                <p class="mt-1 text-sm font-semibold">
                  {{ currentBillingSummary.profile_label }}
                </p>
              </div>
              <Badge variant="outline">
                {{ currentBillingSummary.profile_source === 'saved_profile' ? 'Saved profile' : 'Current settings' }}
              </Badge>
            </div>
            <p class="mt-3 text-sm font-semibold tabular-nums">
              {{ fmtCurrency(currentBillingSummary.price_per_kwh_with_tax) }}/kWh
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
              {{ fmtCurrency(currentBillingSummary.price_per_kwh) }} excl. VAT · VAT {{ fmt(currentBillingSummary.tax_percent, 2) }}%
            </p>
          </div>

          <div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-1">
            <div class="rounded-2xl border border-border/40 bg-background p-4">
              <p class="text-xs text-muted-foreground">
                Energy cost
              </p>
              <p class="mt-1 text-lg font-semibold leading-none tabular-nums">
                {{ fmtCurrency(currentBillingSummary.day.subtotal) }}
              </p>
            </div>
            <div class="rounded-2xl border border-border/40 bg-background p-4">
              <p class="text-xs text-muted-foreground">
                VAT
              </p>
              <p class="mt-1 text-lg font-semibold leading-none tabular-nums">
                {{ fmtCurrency(currentBillingSummary.day.tax_amount) }}
              </p>
            </div>
            <div class="rounded-2xl border border-border/40 bg-background p-4">
              <p class="text-xs text-muted-foreground">
                Total with VAT
              </p>
              <p class="mt-1 text-lg font-semibold leading-none tabular-nums">
                {{ fmtCurrency(currentBillingSummary.day.total_cost) }}
              </p>
            </div>
          </div>

          <div class="rounded-2xl border border-border/40 bg-background p-4">
            <p class="text-xs text-muted-foreground">
              Dominant socket
            </p>
            <p class="mt-1 text-sm font-semibold">
              {{ historyState.topSocket?.name ?? 'Unavailable' }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
              {{ fmt(historyState.topSocket?.energy_kwh ?? 0, 4) }} kWh · {{ historyState.topSocket?.percentage ?? 0 }}%
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

    <Dialog v-model:open="detailOpen">
      <DialogContent class="max-h-[92vh] w-[96vw] overflow-y-auto p-5 sm:max-w-[1180px] sm:p-6 xl:max-w-[1440px]">
        <DialogHeader class="pr-8">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <DialogTitle>
                {{
                  selectedMinute
                    ? `Second-by-second view for ${selectedMinute.minute}`
                    : `Minute-by-minute view for ${selectedHour?.hour ?? '--:--'}`
                }}
              </DialogTitle>
              <DialogDescription>
                {{
                  selectedMinute
                    ? 'Recorded second-level samples inside the selected minute.'
                    : 'Choose a minute slot to drill down to seconds.'
                }}
              </DialogDescription>
            </div>
            <Button
              v-if="selectedMinute"
              variant="outline"
              size="sm"
              class="gap-1.5"
              @click="goBackToMinutes"
            >
              <ChevronLeft class="size-4" />
              Back
            </Button>
          </div>
        </DialogHeader>

        <div
          v-if="selectedHour"
          class="space-y-4"
        >
          <div class="grid gap-2 sm:grid-cols-3">
            <div class="rounded-2xl border border-border/40 bg-background p-3">
              <p class="text-xs text-muted-foreground">
                {{ selectedMinute ? 'Selected minute' : 'Selected hour' }}
              </p>
              <p class="mt-1 text-base font-semibold tabular-nums">
                {{ selectedMinute?.minute ?? selectedHour.hour }}
              </p>
            </div>
            <div class="rounded-2xl border border-border/40 bg-background p-3">
              <p class="text-xs text-muted-foreground">
                Energy
              </p>
              <p class="mt-1 text-base font-semibold tabular-nums">
                {{ fmtEnergy(selectedMinute?.energy_kwh ?? selectedHour.energy_kwh) }}
              </p>
            </div>
            <div class="rounded-2xl border border-border/40 bg-background p-3">
              <p class="text-xs text-muted-foreground">
                Avg / Peak
              </p>
              <p class="mt-1 text-base font-semibold tabular-nums">
                {{ fmtPower(selectedMinute?.avg_power_w ?? selectedHour.avg_power_w, 1) }} / {{ fmtPower(selectedMinute?.peak_power_w ?? selectedHour.peak_power_w, 1) }} W
              </p>
            </div>
          </div>

          <template v-if="!selectedMinute">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <p class="text-sm font-medium">
                Minute timeline
              </p>
              <p class="text-xs text-muted-foreground">
                Scroll to inspect all minute samples inside the selected hour.
              </p>
            </div>

            <div v-if="selectedHourMinutes.length" class="rounded-[24px] border border-border/40 bg-background p-4">
              <div class="h-[20rem] min-h-[20rem] overflow-x-auto overflow-y-hidden">
                <div class="min-w-[980px] h-full">
                  <Chart
                    :key="`minute-${historyState.selectedDate}-${selectedHour?.hour ?? 'none'}-${themeRevision}`"
                    type="line"
                    :data="minuteChartData"
                    :options="minuteChartOptions"
                    class="h-full w-full"
                  />
                </div>
              </div>
            </div>

            <p
              v-else
              class="rounded-2xl border border-dashed border-border/50 bg-background p-4 text-sm text-muted-foreground"
            >
              No minute-level samples are available for this hour.
            </p>
          </template>

          <template v-else>
            <div class="rounded-[24px] border border-border/40 bg-background p-4">
              <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-medium">
                  Second timeline
                </p>
                <p class="text-xs text-muted-foreground">
                  Detailed chart for the selected minute.
                </p>
              </div>

              <div
                v-if="selectedMinuteSeconds.length"
                class="h-[18rem] min-h-[18rem] overflow-x-auto overflow-y-hidden"
              >
                <div class="min-w-[980px] h-full">
                  <Chart
                    :key="`second-${historyState.selectedDate}-${selectedMinute?.minute ?? 'none'}-${themeRevision}`"
                    type="line"
                    :data="secondChartData"
                    :options="secondChartOptions"
                    class="h-full w-full"
                  />
                </div>
              </div>

              <p
                v-else
                class="rounded-2xl border border-dashed border-border/50 bg-background p-4 text-sm text-muted-foreground"
              >
                No second-level samples were recorded for this minute.
              </p>
            </div>

          </template>
        </div>

        <DialogFooter>
          <DialogClose as-child>
            <Button variant="outline">
              Close
            </Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
