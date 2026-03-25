<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

defineProps<{
    title: string;
    value: string | number;
    icon: LucideIcon;
    trend?: string;
    iconColor?: string;
}>();
</script>

<template>
    <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
        <div class="flex items-start justify-between">
            <div
                :class="cn(
                    'flex h-12 w-12 items-center justify-center rounded-lg',
                    iconColor ?? 'bg-primary/10 text-primary',
                )"
            >
                <component :is="icon" class="h-6 w-6" />
            </div>
            <span class="text-3xl font-bold text-foreground">{{ value }}</span>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-sm text-muted-foreground">{{ title }}</span>
            <span v-if="trend" class="text-xs font-medium text-success">{{ trend }}</span>
        </div>
    </div>
</template>
