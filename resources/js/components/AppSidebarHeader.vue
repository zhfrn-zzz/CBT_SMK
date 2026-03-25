<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useAppearance } from '@/composables/useAppearance';
import { getInitials } from '@/composables/useInitials';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const auth = computed(() => page.props.auth as { user: { name: string; email: string; avatar?: string } });
const { resolvedAppearance, updateAppearance } = useAppearance();

function toggleDarkMode() {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <!-- Left: SidebarTrigger + Breadcrumbs -->
        <div class="flex flex-1 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <!-- Right: Dark mode toggle + NotificationBell + User avatar -->
        <div class="flex items-center gap-1">
            <Button
                variant="ghost"
                size="icon-sm"
                class="text-muted-foreground"
                @click="toggleDarkMode"
            >
                <Sun v-if="resolvedAppearance === 'dark'" class="h-5 w-5" />
                <Moon v-else class="h-5 w-5" />
                <span class="sr-only">Toggle dark mode</span>
            </Button>

            <NotificationBell />

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        class="relative rounded-full"
                    >
                        <Avatar class="h-8 w-8 overflow-hidden rounded-full ring-2 ring-primary/20">
                            <AvatarImage
                                v-if="auth.user.avatar"
                                :src="auth.user.avatar"
                                :alt="auth.user.name"
                            />
                            <AvatarFallback class="rounded-full bg-primary/10 text-sm font-medium text-primary">
                                {{ getInitials(auth.user?.name) }}
                            </AvatarFallback>
                        </Avatar>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <UserMenuContent :user="auth.user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
