<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next';

defineProps<{
    title: string;
    value: string | number;
    icon: LucideIcon;
    trend?: string;
    iconColor?: string;
}>();
</script>

<template>
    <div class="rounded-lg border border-border bg-card p-4">
        <div class="flex items-center gap-3">
            <component :is="icon" class="h-5 w-5 shrink-0 text-muted-foreground" />
            <div class="min-w-0 flex-1">
                <span class="text-sm text-muted-foreground">{{ title }}</span>
            </div>
            <span class="text-2xl font-bold text-foreground">{{ value }}</span>
        </div>
        <span v-if="trend" class="mt-1 block text-xs font-medium text-success">{{ trend }}</span>
    </div>
</template>
