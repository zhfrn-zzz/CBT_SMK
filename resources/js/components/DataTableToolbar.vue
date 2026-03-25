<script setup lang="ts">
import { Search, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

defineProps<{
    searchPlaceholder?: string;
    modelValue?: string;
}>();

defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-1 items-center gap-2">
            <div class="relative w-full max-w-sm">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    :model-value="modelValue"
                    :placeholder="searchPlaceholder ?? 'Cari...'"
                    class="pl-9"
                    @update:model-value="$emit('update:modelValue', $event as string)"
                />
            </div>
            <Button
                v-if="modelValue"
                variant="ghost"
                size="icon-sm"
                @click="$emit('update:modelValue', '')"
            >
                <X class="h-4 w-4" />
            </Button>
            <slot name="filters" />
        </div>
        <div class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
