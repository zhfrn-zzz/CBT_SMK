<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next';
import { InboxIcon } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        icon?: LucideIcon;
        title?: string;
        description?: string;
    }>(),
    {
        icon: () => InboxIcon,
        title: 'Tidak ada data',
        description: 'Belum ada data yang tersedia saat ini.',
    },
);
</script>

<template>
    <div class="flex flex-col items-center justify-center py-16 text-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-muted">
            <component :is="icon" class="h-8 w-8 text-muted-foreground" />
        </div>
        <h3 class="mt-4 text-lg font-semibold text-foreground">{{ title }}</h3>
        <p class="mt-1 max-w-sm text-sm text-muted-foreground">{{ description }}</p>
        <div v-if="$slots.action" class="mt-4">
            <slot name="action" />
        </div>
    </div>
</template>
