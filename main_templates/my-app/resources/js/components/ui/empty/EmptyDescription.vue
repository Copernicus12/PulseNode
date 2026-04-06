<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
    class?: HTMLAttributes['class']
}>()
</script>

<template>
    <p
        data-slot="empty-description"
        :class="cn('max-w-lg text-sm leading-7 text-muted-foreground', props.class)"
    >
        <slot />
    </p>
</template>
