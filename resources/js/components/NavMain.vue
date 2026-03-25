<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavGroup, NavItem } from '@/types';

const props = defineProps<{
    groups?: NavGroup[];
    items?: NavItem[];
    label?: string;
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <!-- Grouped mode -->
    <template v-if="groups && groups.length > 0">
        <SidebarGroup v-for="group in groups" :key="group.label" class="px-2 py-0">
            <SidebarGroupLabel class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                {{ group.label }}
            </SidebarGroupLabel>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in group.items" :key="item.title">
                    <SidebarMenuButton
                        as-child
                        :is-active="isCurrentUrl(item.href)"
                        :tooltip="item.title"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    </template>

    <!-- Flat mode (backward compatible) -->
    <SidebarGroup v-else-if="items && items.length > 0" class="px-2 py-0">
        <SidebarGroupLabel>{{ label ?? 'Menu Utama' }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
