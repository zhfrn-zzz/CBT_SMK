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
    assignment: 'bg-yellow-500',
    attendance: 'bg-blue-500',
};
</script>

<template>
    <div
        class="min-h-[80px] border rounded-md p-1 cursor-pointer transition-colors hover:bg-muted/50"
        :class="{
            'opacity-40': !isCurrentMonth,
            'ring-2 ring-primary': isToday,
        }"
        @click="emit('select', date)"
    >
        <div class="text-xs font-medium mb-1" :class="isToday ? 'text-primary font-bold' : 'text-muted-foreground'">
            {{ day }}
        </div>
        <div class="space-y-0.5">
            <div
                v-for="(event, idx) in events.slice(0, 3)"
                :key="idx"
                class="flex items-center gap-1"
            >
                <div class="size-1.5 rounded-full shrink-0" :class="typeColors[event.type] ?? 'bg-gray-500'" />
                <span class="text-xs truncate">{{ event.title }}</span>
            </div>
            <div v-if="events.length > 3" class="text-xs text-muted-foreground">
                +{{ events.length - 3 }} lainnya
            </div>
        </div>
    </div>
</template>
