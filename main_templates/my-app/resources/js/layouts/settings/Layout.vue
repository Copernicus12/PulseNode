<script setup lang="ts">
import SettingsTopBar from '@/components/SettingsTopBar.vue';
import AppLayout from '@/layouts/AppLayout.vue';

withDefaults(
    defineProps<{
        wide?: boolean;
    }>(),
    {
        wide: false,
    },
);
</script>

<template>
    <AppLayout :sidebar-default-open="true" :show-header="false">
        <div class="pt-3">
            <SettingsTopBar />
        </div>

        <div class="px-2 pt-1 pb-6 lg:px-4">
            <section
                :class="
                    wide
                        ? 'mx-auto max-w-[90rem] space-y-6'
                        : 'mx-auto max-w-5xl space-y-6'
                "
            >
                <slot />
            </section>
        </div>
    </AppLayout>
</template>
