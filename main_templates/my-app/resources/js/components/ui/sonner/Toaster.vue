<script setup lang="ts">
import 'vue-sonner/style.css'

import { computed } from 'vue'
import type { ToasterProps } from 'vue-sonner'
import { Toaster as Sonner } from 'vue-sonner'
import { useAppearance } from '@/composables/useAppearance'

const props = defineProps<ToasterProps>()
const { resolvedAppearance } = useAppearance()

const theme = computed(() =>
    props.theme === 'light' || props.theme === 'dark'
        ? props.theme
        : resolvedAppearance.value,
)
</script>

<template>
    <Sonner v-bind="props" :theme="theme" />
</template>
