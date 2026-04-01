<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
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
    <AppShell variant="sidebar" :sidebar-default-open="sidebarDefaultOpen">
        <AppSidebar :collapsible="sidebarCollapsible" />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader v-if="showHeader" :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
    </AppShell>
</template>
