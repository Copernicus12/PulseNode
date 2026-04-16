<script setup lang="ts">
import { computed } from 'vue';
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import { cn } from '@/lib/utils';
import type { Appearance } from '@/types';

type Props = {
    compact?: boolean;
    align?: 'start' | 'end';
    sideOffset?: number;
    buttonClass?: string;
    contentClass?: string;
};

const props = withDefaults(defineProps<Props>(), {
    compact: false,
    align: 'end',
    sideOffset: 8,
    buttonClass: '',
    contentClass: '',
});

const { appearance, resolvedAppearance, updateAppearance } = useAppearance();

const options: Array<{
    value: Appearance;
    label: string;
    description: string;
    icon: typeof Sun;
}> = [
    {
        value: 'light',
        label: 'Light',
        description: 'Use the brighter interface palette.',
        icon: Sun,
    },
    {
        value: 'dark',
        label: 'Dark',
        description: 'Use the darker interface palette.',
        icon: Moon,
    },
    {
        value: 'system',
        label: 'System',
        description: 'Follow the operating system automatically.',
        icon: Monitor,
    },
];

const activeOption = computed(
    () =>
        options.find((option) => option.value === appearance.value) ??
        options[2],
);

function handleValueChange(value: string) {
    if (value === 'light' || value === 'dark' || value === 'system') {
        updateAppearance(value);
    }
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                :variant="compact ? 'ghost' : 'outline'"
                :size="compact ? 'icon' : 'sm'"
                :class="
                    cn(
                        compact
                            ? 'rounded-full text-muted-foreground hover:text-foreground'
                            : 'rounded-full border-border/50 bg-background/85 px-3 text-foreground shadow-xs backdrop-blur-sm',
                        buttonClass,
                    )
                "
                aria-label="Change appearance"
            >
                <component :is="activeOption.icon" class="size-4" />
                <span v-if="!compact" class="text-xs font-semibold">
                    {{ activeOption.label }}
                </span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            :align="align"
            :side-offset="sideOffset"
            :class="cn('w-72 rounded-3xl p-2', contentClass)"
        >
            <div class="px-2 py-1.5">
                <p class="text-xs font-semibold">Appearance</p>
                <p class="text-[11px] leading-5 text-muted-foreground">
                    Current mode: {{ activeOption.label }}, rendered as
                    {{ resolvedAppearance }}.
                </p>
            </div>

            <DropdownMenuSeparator />

            <DropdownMenuRadioGroup
                :model-value="appearance"
                @update:model-value="handleValueChange"
            >
                <DropdownMenuRadioItem
                    v-for="option in options"
                    :key="option.value"
                    :value="option.value"
                    class="rounded-2xl px-2 py-2.5"
                >
                    <component :is="option.icon" class="size-4" />
                    <div class="flex min-w-0 flex-col gap-0.5">
                        <span class="font-medium text-foreground">
                            {{ option.label }}
                        </span>
                        <span
                            class="text-[11px] leading-4 text-muted-foreground"
                        >
                            {{ option.description }}
                        </span>
                    </div>
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
