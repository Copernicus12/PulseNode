<script setup lang="ts">
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { DatePicker } from '@/components/ui/date-picker'
import {
  Field,
  FieldDescription,
  FieldGroup,
  FieldLabel,
  FieldLegend,
  FieldSeparator,
  FieldSet,
} from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'

type GuardPolicy = {
  id?: string | null
  enabled?: boolean
  status?: 'active' | 'paused' | 'scheduled' | 'expired' | 'empty'
  status_label?: string
  status_tone?: string
  scope_mode?: 'common' | 'per_socket'
  common_threshold_amps?: number
  socket_threshold_amps_1?: number
  socket_threshold_amps_2?: number
  socket_threshold_amps_3?: number
  action?: 'off-1' | 'off-2' | 'off-3' | 'off-all'
  start_date?: string
  has_end_date?: boolean
  end_date?: string | null
  notes?: string | null
  created_at?: string | null
  updated_at?: string | null
  last_triggered_at?: string | null
  last_triggered_reason?: string | null
  pause_url?: string | null
  resume_url?: string | null
  delete_url?: string | null
}

const props = defineProps<{
  initialPolicy?: GuardPolicy
  saveUrl?: string
}>()

const initialPolicy = props.initialPolicy ?? {}

function localDateString(): string {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const enabled = ref<boolean>(Boolean(initialPolicy.enabled ?? false))
const scopeMode = ref<'common' | 'per_socket'>(
  initialPolicy.scope_mode === 'per_socket' ? 'per_socket' : 'common',
)
const commonThreshold = ref<string>(String(initialPolicy.common_threshold_amps ?? 10))
const socketThreshold1 = ref<string>(String(initialPolicy.socket_threshold_amps_1 ?? 10))
const socketThreshold2 = ref<string>(String(initialPolicy.socket_threshold_amps_2 ?? 10))
const socketThreshold3 = ref<string>(String(initialPolicy.socket_threshold_amps_3 ?? 10))
const action = ref<'off-1' | 'off-2' | 'off-3' | 'off-all'>(
  initialPolicy.action ?? 'off-all',
)
const todayDate = localDateString()
const startDate = ref<string>(initialPolicy.start_date ?? todayDate)
const hasEndDate = ref<boolean>(Boolean(initialPolicy.has_end_date ?? false))
const endDate = ref<string>(initialPolicy.end_date ?? '')
const notes = ref<string>('')

const isSubmitting = ref(false)
const guardMessage = ref('Save a policy to add it to the list below.')

const scopeOptions = [
  { value: 'common', label: 'Common' },
  { value: 'per_socket', label: 'Per socket' },
]

function getCsrfToken(): string {
  const meta = document.querySelector('meta[name="csrf-token"]')
  return meta ? (meta.getAttribute('content') || '') : ''
}

async function submitMutation(url: string, method: 'POST' | 'DELETE', payload?: Record<string, unknown>) {
  const response = await fetch(url, {
    method,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': getCsrfToken(),
    },
    credentials: 'same-origin',
    body: payload ? JSON.stringify(payload) : undefined,
  })

  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new Error((data && data.message) ? data.message : 'Operation failed')
  }

  return data
}

function buildPayload() {
  return {
    enabled: enabled.value,
    scope_mode: scopeMode.value,
    common_threshold_amps: Number.parseFloat(commonThreshold.value || '0'),
    socket_threshold_amps_1: Number.parseFloat(socketThreshold1.value || '0'),
    socket_threshold_amps_2: Number.parseFloat(socketThreshold2.value || '0'),
    socket_threshold_amps_3: Number.parseFloat(socketThreshold3.value || '0'),
    action: action.value,
    start_date: startDate.value,
    has_end_date: hasEndDate.value,
    end_date: hasEndDate.value ? endDate.value : null,
    notes: notes.value || null,
  }
}

async function savePolicy() {
  const endpoint = props.saveUrl || '/power-strip/guard-policy'
  isSubmitting.value = true

  try {
    await submitMutation(endpoint, 'POST', buildPayload())
    window.location.reload()
  } catch (error) {
    guardMessage.value = error instanceof Error ? error.message : 'Failed to save guard policy.'
  } finally {
    isSubmitting.value = false
  }
}

</script>

<template>
  <div class="w-full font-sans text-foreground">
    <form @submit.prevent="savePolicy">
      <FieldGroup class="gap-4">
        <FieldSet>
          <FieldLegend class="text-lg font-bold tracking-tight">
            Safety Guard
          </FieldLegend>
          <FieldDescription class="text-xs text-muted-foreground">
            Create configurable, reusable policies for everyday scenarios.
          </FieldDescription>

          <FieldGroup class="gap-4">
            <Field orientation="horizontal" class="items-start gap-3">
              <Checkbox
                id="guard-enabled-ui"
                v-model="enabled"
              />
              <div class="space-y-0.5">
                <FieldLabel for="guard-enabled-ui" class="text-sm font-medium leading-5">
                  Enable guard
                </FieldLabel>
                <FieldDescription class="text-xs text-muted-foreground">
                  When disabled, the policy is saved but stays paused.
                </FieldDescription>
              </div>
            </Field>

            <Field>
              <FieldLabel for="guard-scope-ui" class="text-sm font-medium">
                Threshold scope
              </FieldLabel>
              <Select v-model="scopeMode">
                <SelectTrigger id="guard-scope-ui" class="w-full">
                  <SelectValue placeholder="Scope" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="option in scopeOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <FieldDescription class="text-xs text-muted-foreground">
                Common checks total current. Per socket checks each socket separately.
              </FieldDescription>
            </Field>

            <Field>
              <FieldLabel v-if="scopeMode === 'common'" for="guard-threshold-ui" class="text-sm font-medium">
                Common current threshold
                <span
                  class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-muted text-[10px] text-muted-foreground"
                  title="When the total current goes above this value, the guard can act."
                >
                  i
                </span>
              </FieldLabel>
              <FieldLabel v-else class="text-sm font-medium">
                Per socket current thresholds
              </FieldLabel>

              <div v-if="scopeMode === 'common'" class="relative">
                <Input
                  id="guard-threshold-ui"
                  v-model="commonThreshold"
                  type="number"
                  min="0.1"
                  max="100"
                  step="0.1"
                  class="pr-9"
                  required
                />
                <span class="pointer-events-none absolute inset-y-0 right-3 inline-flex items-center text-xs text-muted-foreground">
                  A
                </span>
              </div>

              <div v-else class="grid gap-3 md:grid-cols-3">
                <div class="space-y-2">
                  <FieldLabel for="guard-threshold-1-ui" class="text-sm font-medium">Socket 1</FieldLabel>
                  <Input id="guard-threshold-1-ui" v-model="socketThreshold1" type="number" min="0.1" max="100" step="0.1" required />
                </div>
                <div class="space-y-2">
                  <FieldLabel for="guard-threshold-2-ui" class="text-sm font-medium">Socket 2</FieldLabel>
                  <Input id="guard-threshold-2-ui" v-model="socketThreshold2" type="number" min="0.1" max="100" step="0.1" required />
                </div>
                <div class="space-y-2">
                  <FieldLabel for="guard-threshold-3-ui" class="text-sm font-medium">Socket 3</FieldLabel>
                  <Input id="guard-threshold-3-ui" v-model="socketThreshold3" type="number" min="0.1" max="100" step="0.1" required />
                </div>
              </div>
            </Field>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
              <Field>
                <FieldLabel for="guard-action-ui" class="text-sm font-medium">
                  Shutdown action
                </FieldLabel>
                <Select v-model="action">
                  <SelectTrigger id="guard-action-ui" class="w-full">
                    <SelectValue placeholder="Action" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="off-3">
                      Turn off socket 3
                    </SelectItem>
                    <SelectItem value="off-2">
                      Turn off socket 2
                    </SelectItem>
                    <SelectItem value="off-1">
                      Turn off socket 1
                    </SelectItem>
                    <SelectItem value="off-all">
                      Turn off all sockets
                    </SelectItem>
                  </SelectContent>
                </Select>
              </Field>

              <Field>
                <FieldLabel for="guard-start-date-ui" class="text-sm font-medium">
                  Start date
                </FieldLabel>
                <DatePicker
                  id="guard-start-date-ui"
                  v-model="startDate"
                  placeholder="Select day"
                  class="h-10"
                  :min="todayDate"
                />
                <FieldDescription class="text-xs text-muted-foreground">
                  The guard becomes eligible starting on this date.
                </FieldDescription>
              </Field>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
              <Field>
                <Field orientation="horizontal" class="items-start gap-3">
                  <Checkbox
                    id="guard-end-date-enabled-ui"
                    v-model="hasEndDate"
                  />
                  <div class="space-y-0.5">
                    <FieldLabel for="guard-end-date-enabled-ui" class="text-sm font-medium leading-5">
                      Add end date
                    </FieldLabel>
                    <FieldDescription class="text-xs text-muted-foreground">
                      Turn this on if the guard should stop automatically later.
                    </FieldDescription>
                  </div>
                </Field>
              </Field>

              <Field :class="hasEndDate ? '' : 'opacity-60'">
                <FieldLabel for="guard-end-date-ui" class="text-sm font-medium">
                  End date
                </FieldLabel>
                <DatePicker
                  id="guard-end-date-ui"
                  v-model="endDate"
                  placeholder="Select day"
                  class="h-10"
                  :min="todayDate"
                  :disabled="!hasEndDate"
                />
                <FieldDescription class="text-xs text-muted-foreground">
                  The guard stays active until this date, inclusive.
                </FieldDescription>
              </Field>
            </div>
          </FieldGroup>
        </FieldSet>

        <FieldSeparator class="my-1" />

        <FieldSet>
          <FieldGroup>
            <Field>
              <FieldLabel for="guard-notes-ui" class="text-sm font-medium">
                Comments
              </FieldLabel>
              <Textarea
                id="guard-notes-ui"
                v-model="notes"
                placeholder="Add any additional comments"
                class="resize-none"
              />
            </Field>
          </FieldGroup>
        </FieldSet>

        <Field orientation="horizontal" class="items-center justify-between gap-3 pt-1">
          <Button type="submit" :disabled="isSubmitting">
            Save policy
          </Button>
          <p class="text-xs text-muted-foreground">
            {{ guardMessage }}
          </p>
        </Field>
      </FieldGroup>
    </form>
  </div>
</template>
