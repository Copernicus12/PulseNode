<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = withDefaults(
    defineProps<{
        class?: HTMLAttributes['class']
        variant?: 'default' | 'icon'
    }>(),
    {
        variant: 'default',
    },
)
</script>

<template>
    <div
        data-slot="empty-media"
        :class="
            cn(
                'flex items-center justify-center rounded-3xl bg-muted/60 text-foreground',
                props.variant === 'icon' ? 'h-18 w-18 [&_svg]:h-8 [&_svg]:w-8' : 'px-4 py-3',
                props.class,
            )
        "
    >
        <slot />
    </div>
</template>
