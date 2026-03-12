<script setup lang="ts">
import type { DateValue } from "@internationalized/date"
import type { HTMLAttributes } from "vue"
import { computed, ref } from "vue"
import { CalendarIcon } from "lucide-vue-next"
import { parseDate } from "@internationalized/date"
import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { cn } from "@/lib/utils"

type Props = {
  modelValue?: string
  placeholder?: string
  class?: HTMLAttributes["class"]
  disabled?: boolean
  min?: string
  max?: string
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: "Selecteaza o data",
  modelValue: undefined,
  class: undefined,
  disabled: false,
  min: undefined,
  max: undefined,
})

const emit = defineEmits<{
  (e: "update:modelValue", value: string | undefined): void
  (e: "change", value: string | undefined): void
}>()

const open = ref(false)

function parseIsoDate(value?: string): DateValue | undefined {
  if (!value) return undefined

  try {
    return parseDate(value)
  } catch (_error) {
    return undefined
  }
}

const calendarValue = computed(() => parseIsoDate(props.modelValue))
const minValue = computed(() => parseIsoDate(props.min))
const maxValue = computed(() => parseIsoDate(props.max))

const label = computed(() => {
  if (!props.modelValue) return props.placeholder

  const [year, month, day] = props.modelValue.split("-")
  if (!year || !month || !day) return props.modelValue

  return `${day}/${month}/${year}`
})

function handleSelect(value: DateValue | undefined): void {
  const next = value ? value.toString() : undefined
  emit("update:modelValue", next)
  emit("change", next)
  open.value = false
}
</script>

<template>
  <Popover v-model:open="open">
    <PopoverTrigger as-child>
      <Button
        type="button"
        variant="outline"
        :disabled="disabled"
        :class="cn('w-full justify-start text-left font-normal', !modelValue && 'text-muted-foreground', props.class)"
      >
        <CalendarIcon class="mr-2 h-4 w-4" />
        <span>{{ label }}</span>
      </Button>
    </PopoverTrigger>

    <PopoverContent class="w-auto p-0" align="start">
      <Calendar
        mode="single"
        :model-value="calendarValue"
        :min-value="minValue"
        :max-value="maxValue"
        @update:model-value="(value) => handleSelect(value as DateValue | undefined)"
      />
    </PopoverContent>
  </Popover>
</template>
