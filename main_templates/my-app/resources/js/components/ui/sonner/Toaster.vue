<script setup lang="ts">
import 'vue-sonner/style.css'

import type { ToasterProps } from 'vue-sonner'
import { Toaster as Sonner } from 'vue-sonner'

defineProps<ToasterProps>()
</script>

<template>
    <Sonner v-bind="$props" />
</template>
