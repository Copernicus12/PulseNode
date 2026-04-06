<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
    class?: HTMLAttributes['class']
}>()
</script>

<template>
    <div
        data-slot="empty"
        :class="
            cn(
                'flex min-h-[20rem] flex-col items-center justify-center gap-6 rounded-3xl border border-dashed border-border/50 bg-background/40 px-6 py-10 text-center',
                props.class,
            )
        "
    >
        <slot />
    </div>
</template>
