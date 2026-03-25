<script setup lang="ts">
import type { CalendarEvent } from '@/types';

defineProps<{
    day: number;
    isCurrentMonth: boolean;
    isToday: boolean;
    events: CalendarEvent[];
    date: string;
}>();

const emit = defineEmits<{
    select: [date: string];
}>();

const typeColors: Record<string, string> = {
    exam: 'bg-red-500',
    assignment: 'bg-amber-500',
    attendance: 'bg-blue-500',
};
</script>

<template>
    <div
        class="min-h-[80px] cursor-pointer border-b border-r p-2 transition-colors hover:bg-muted/50 sm:min-h-[100px]"
        :class="{
            'text-muted-foreground/30': !isCurrentMonth,
            'bg-primary/5': isToday,
        }"
        @click="emit('select', date)"
    >
        <div class="mb-1 text-xs font-medium" :class="isToday ? 'font-bold text-primary' : ''">
            {{ day }}
        </div>
        <div class="space-y-0.5">
            <div
                v-for="(event, idx) in events.slice(0, 3)"
                :key="idx"
                class="flex items-center gap-1"
            >
                <div class="size-2 shrink-0 rounded-full" :class="typeColors[event.type] ?? 'bg-gray-500'" />
                <span class="truncate text-[10px] leading-tight sm:text-xs">{{ event.title }}</span>
            </div>
            <div v-if="events.length > 3" class="text-[10px] text-muted-foreground">
                +{{ events.length - 3 }} lainnya
            </div>
        </div>
    </div>
</template>
