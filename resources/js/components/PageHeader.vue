<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next';

defineProps<{
    title: string;
    description?: string;
    icon?: LucideIcon;
}>();
</script>

<template>
    <div class="flex items-center justify-between">
        <div class="space-y-1">
            <div class="flex items-center gap-3">
                <div
                    v-if="icon"
                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10"
                >
                    <component :is="icon" class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-foreground">{{ title }}</h1>
                    <p v-if="description" class="text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
