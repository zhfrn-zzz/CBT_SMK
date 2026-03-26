<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next';

defineProps<{
    title: string;
    description?: string;
    icon?: LucideIcon;
}>();
</script>

<template>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1 min-w-0">
            <div class="flex items-center gap-3">
                <div
                    v-if="icon"
                    class="hidden sm:flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 shrink-0"
                >
                    <component :is="icon" class="h-5 w-5 text-primary" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl sm:text-2xl font-bold text-foreground truncate">{{ title }}</h1>
                    <p v-if="description" class="text-sm text-muted-foreground truncate">
                        {{ description }}
                    </p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0 overflow-x-auto">
            <slot name="actions" />
        </div>
    </div>
</template>
