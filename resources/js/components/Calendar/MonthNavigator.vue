<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

defineProps<{
    monthLabel: string;
}>();

const emit = defineEmits<{
    prev: [];
    next: [];
    today: [];
}>();
</script>

<template>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold">{{ monthLabel }}</h2>
        <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" @click="emit('today')">Hari ini</Button>
            <Button variant="outline" size="icon" @click="emit('prev')">
                <ChevronLeft class="size-4" />
            </Button>
            <Button variant="outline" size="icon" @click="emit('next')">
                <ChevronRight class="size-4" />
            </Button>
        </div>
    </div>
</template>
