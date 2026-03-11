<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
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

type GuardWindow = Window & {
  saveGuardPolicy?: () => void
  simulateGuard?: () => void
}

const threshold = ref<string>('1800')
const action = ref<string>('off-3')
const startMonth = ref<string>('')
const startYear = ref<string>('')
const sameAsBasePolicy = ref<boolean>(true)
const notes = ref<string>('')

const months = [
  { value: '01', label: '01' },
  { value: '02', label: '02' },
  { value: '03', label: '03' },
  { value: '04', label: '04' },
  { value: '05', label: '05' },
  { value: '06', label: '06' },
  { value: '07', label: '07' },
  { value: '08', label: '08' },
  { value: '09', label: '09' },
  { value: '10', label: '10' },
  { value: '11', label: '11' },
  { value: '12', label: '12' },
]

const currentYear = new Date().getFullYear()
const years = Array.from({ length: 8 }, (_, index) => String(currentYear + index))

const startDate = computed(() =>
  startYear.value && startMonth.value ? `${startYear.value}-${startMonth.value}-01` : '',
)

onMounted(() => {
  try {
    const raw = localStorage.getItem('powerStripGuard')
    if (raw) {
      const policy = JSON.parse(raw) as {
        threshold?: number | string
        action?: string
        startDate?: string
      }

      if (policy.threshold !== undefined && policy.threshold !== null) {
        threshold.value = String(policy.threshold)
      }
      if (policy.action) {
        action.value = policy.action
      }
      if (policy.startDate) {
        const parts = String(policy.startDate).split('-')
        if (parts.length >= 2) {
          startYear.value = parts[0] || ''
          startMonth.value = parts[1] || ''
        }
      }
    }
  } catch (_) {
    // Keep defaults if the saved policy is malformed.
  }

  try {
    const savedNotes = localStorage.getItem('powerStripGuardNotes')
    if (savedNotes) notes.value = savedNotes
  } catch (_) {
    // Ignore local storage access errors.
  }
})

function savePolicy() {
  try {
    localStorage.setItem('powerStripGuardNotes', notes.value)
  } catch (_) {
    // Ignore local storage access errors.
  }

  ;(window as GuardWindow).saveGuardPolicy?.()
}

function runTest() {
  try {
    localStorage.setItem('powerStripGuardNotes', notes.value)
  } catch (_) {
    // Ignore local storage access errors.
  }

  ;(window as GuardWindow).simulateGuard?.()
}
</script>

<template>
  <div class="w-full font-sans text-foreground">
    <form @submit.prevent="savePolicy">
      <FieldGroup class="gap-5">
        <FieldSet>
          <FieldLegend class="text-lg font-bold tracking-tight">
            Safety Guard
          </FieldLegend>
          <FieldDescription class="text-sm text-muted-foreground">
            All protection rules are secure and local to your workspace.
          </FieldDescription>

          <FieldGroup>
            <Field>
              <FieldLabel for="guard-threshold-ui" class="text-sm font-medium">
                Power Threshold
                <span
                  class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-muted text-[10px] text-muted-foreground"
                  title="Exemplu: la 1800W se poate opri automat Socket 3."
                >
                  i
                </span>
              </FieldLabel>
              <div class="relative">
                <Input
                  id="guard-threshold-ui"
                  v-model="threshold"
                  type="number"
                  min="600"
                  max="2800"
                  step="50"
                  class="pr-9"
                  required
                />
                <span class="pointer-events-none absolute inset-y-0 right-3 inline-flex items-center text-xs text-muted-foreground">
                  W
                </span>
              </div>
              <FieldDescription class="text-sm text-muted-foreground">
                Recommended range: 1200W - 2200W.
              </FieldDescription>
            </Field>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <Field>
                <FieldLabel for="guard-action-ui" class="text-sm font-medium">
                  Guard Action
                </FieldLabel>
                <Select v-model="action">
                  <SelectTrigger id="guard-action-ui" class="w-full">
                    <SelectValue placeholder="Action" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="off-3">
                      Cut socket 3
                    </SelectItem>
                    <SelectItem value="off-2">
                      Cut socket 2
                    </SelectItem>
                    <SelectItem value="off-all">
                      Cut all sockets
                    </SelectItem>
                  </SelectContent>
                </Select>
              </Field>

              <Field>
                <FieldLabel for="guard-start-month-ui" class="text-sm font-medium">
                  Month
                </FieldLabel>
                <Select v-model="startMonth">
                  <SelectTrigger id="guard-start-month-ui" class="w-full">
                    <SelectValue placeholder="MM" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="month in months"
                      :key="month.value"
                      :value="month.value"
                    >
                      {{ month.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </Field>

              <Field>
                <FieldLabel for="guard-start-year-ui" class="text-sm font-medium">
                  Year
                </FieldLabel>
                <Select v-model="startYear">
                  <SelectTrigger id="guard-start-year-ui" class="w-full">
                    <SelectValue placeholder="YYYY" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="year in years"
                      :key="year"
                      :value="year"
                    >
                      {{ year }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </Field>
            </div>
          </FieldGroup>
        </FieldSet>

        <FieldSeparator />

        <FieldSet>
          <FieldLegend variant="label" class="text-base font-semibold">
            Policy Behavior
          </FieldLegend>
          <FieldDescription class="text-sm text-muted-foreground">
            Choose how this guard should behave once configured.
          </FieldDescription>
          <FieldGroup>
            <Field orientation="horizontal">
              <Checkbox
                id="guard-base-policy-checkbox"
                v-model="sameAsBasePolicy"
              />
              <FieldLabel
                for="guard-base-policy-checkbox"
                class="text-sm font-normal"
              >
                Enable pre-shutdown simulation log
              </FieldLabel>
            </Field>
          </FieldGroup>
        </FieldSet>

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

        <Field orientation="horizontal" class="gap-2">
          <Button type="submit">
            Save policy
          </Button>
          <Button variant="outline" type="button" @click="runTest">
            Run test
          </Button>
        </Field>

        <p id="guard-message" class="text-sm text-muted-foreground">
          No action executed.
        </p>
      </FieldGroup>
    </form>

    <input id="guard-threshold" type="hidden" :value="threshold">
    <input id="guard-action" type="hidden" :value="action">
    <input id="guard-start-date" type="hidden" :value="startDate">
    <input id="guard-notes" type="hidden" :value="notes">
  </div>
</template>
