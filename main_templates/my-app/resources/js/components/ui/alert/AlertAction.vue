<script setup lang="ts">
import type { ButtonHTMLAttributes, HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
    class?: HTMLAttributes['class']
    type?: ButtonHTMLAttributes['type']
}>()
</script>

<template>
    <button
        data-slot="alert-action"
        :type="props.type ?? 'button'"
        :class="cn('inline-flex h-8 w-fit items-center rounded-md border border-current/20 px-3 text-xs font-medium transition hover:bg-current/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current/30', props.class)"
    >
        <slot />
    </button>
</template>
