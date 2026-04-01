<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { SidebarProps } from '@/components/ui/sidebar';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    sidebarDefaultOpen?: boolean;
    sidebarCollapsible?: SidebarProps['collapsible'];
    showHeader?: boolean;
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    sidebarDefaultOpen: undefined,
    sidebarCollapsible: 'icon',
    showHeader: true,
});
</script>

<template>
    <AppLayout
        :breadcrumbs="breadcrumbs"
        :sidebar-default-open="sidebarDefaultOpen"
        :sidebar-collapsible="sidebarCollapsible"
        :show-header="showHeader"
    >
        <slot />
    </AppLayout>
</template>
