<script setup lang="ts">
import { ref } from 'vue'
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select'

type FilterState = {
    level: string | null
    type: string | null
    sortBy: string
    perPage: number
}

const props = defineProps<{
    action: string
    filters: FilterState
    levelOptions: Record<string, string>
    typeOptions: string[]
    sortOptions: Record<string, string>
    perPageOptions: number[]
}>()

const formRef = ref<HTMLFormElement | null>(null)
const level = ref(props.filters.level ?? '')
const type = ref(props.filters.type ?? '')
const sortBy = ref(props.filters.sortBy ?? 'newest')
const perPage = ref(String(props.filters.perPage ?? 10))

function typeLabel(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase())
}

function submit(): void {
    window.requestAnimationFrame(() => {
        formRef.value?.submit()
    })
}
</script>

<template>
    <form
        ref="formRef"
        method="GET"
        :action="action"
        class="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
    >
        <div class="w-full">
            <NativeSelect
                v-model="level"
                name="filter_level"
                class="h-11 rounded-2xl bg-background/90 px-4 ring-1 ring-border/40"
                @update:model-value="submit"
            >
                <NativeSelectOption value="">
                    All levels
                </NativeSelectOption>
                <NativeSelectOption
                    v-for="(label, value) in levelOptions"
                    :key="value"
                    v-if="value !== 'all'"
                    :value="value"
                >
                    {{ label }}
                </NativeSelectOption>
            </NativeSelect>
        </div>

        <div class="w-full">
            <NativeSelect
                v-model="type"
                name="filter_type"
                class="h-11 rounded-2xl bg-background/90 px-4 ring-1 ring-border/40"
                @update:model-value="submit"
            >
                <NativeSelectOption value="">
                    All types
                </NativeSelectOption>
                <NativeSelectOption
                    v-for="typeOption in typeOptions"
                    :key="typeOption"
                    :value="typeOption"
                >
                    {{ typeLabel(typeOption) }}
                </NativeSelectOption>
            </NativeSelect>
        </div>

        <div class="w-full">
            <NativeSelect
                v-model="sortBy"
                name="sort_by"
                class="h-11 rounded-2xl bg-background/90 px-4 ring-1 ring-border/40"
                @update:model-value="submit"
            >
                <NativeSelectOption
                    v-for="(label, value) in sortOptions"
                    :key="value"
                    :value="value"
                >
                    {{ label }}
                </NativeSelectOption>
            </NativeSelect>
        </div>

        <div class="w-full">
            <NativeSelect
                v-model="perPage"
                name="per_page"
                class="h-11 rounded-2xl bg-background/90 px-4 ring-1 ring-border/40"
                @update:model-value="submit"
            >
                <NativeSelectOption
                    v-for="option in perPageOptions"
                    :key="option"
                    :value="String(option)"
                >
                    {{ option }} / page
                </NativeSelectOption>
            </NativeSelect>
        </div>
    </form>
</template>
