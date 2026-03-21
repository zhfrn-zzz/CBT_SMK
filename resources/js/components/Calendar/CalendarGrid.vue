<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import MonthNavigator from './MonthNavigator.vue';
import CalendarDay from './CalendarDay.vue';
import { Badge } from '@/components/ui/badge';
import type { CalendarEvent } from '@/types';

const props = defineProps<{
    events: CalendarEvent[];
}>();

const emit = defineEmits<{
    monthChange: [year: number, month: number];
}>();

const now = new Date();
const currentYear = ref(now.getFullYear());
const currentMonth = ref(now.getMonth()); // 0-based

const selectedDate = ref<string | null>(null);

const monthLabel = computed(() => {
    const date = new Date(currentYear.value, currentMonth.value);
    return date.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
});

const dayNames = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

interface DayCell {
    date: string;
    day: number;
    isCurrentMonth: boolean;
    isToday: boolean;
    events: CalendarEvent[];
}

const days = computed<DayCell[]>(() => {
    const year = currentYear.value;
    const month = currentMonth.value;

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);

    // Monday = 0, Sunday = 6
    let startDow = firstDay.getDay() - 1;
    if (startDow < 0) startDow = 6;

    const cells: DayCell[] = [];
    const today = new Date();
    const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

    // Previous month padding
    const prevMonth = new Date(year, month, 0);
    for (let i = startDow - 1; i >= 0; i--) {
        const d = prevMonth.getDate() - i;
        const dateStr = formatDate(prevMonth.getFullYear(), prevMonth.getMonth(), d);
        cells.push({
            date: dateStr,
            day: d,
            isCurrentMonth: false,
            isToday: dateStr === todayStr,
            events: getEventsForDate(dateStr),
        });
    }

    // Current month
    for (let d = 1; d <= lastDay.getDate(); d++) {
        const dateStr = formatDate(year, month, d);
        cells.push({
            date: dateStr,
            day: d,
            isCurrentMonth: true,
            isToday: dateStr === todayStr,
            events: getEventsForDate(dateStr),
        });
    }

    // Next month padding
    const remaining = 42 - cells.length;
    for (let d = 1; d <= remaining; d++) {
        const dateStr = formatDate(year, month + 1, d);
        cells.push({
            date: dateStr,
            day: d,
            isCurrentMonth: false,
            isToday: dateStr === todayStr,
            events: getEventsForDate(dateStr),
        });
    }

    return cells;
});

const selectedEvents = computed(() => {
    if (!selectedDate.value) return [];
    return props.events.filter((e) => e.date === selectedDate.value);
});

function formatDate(y: number, m: number, d: number): string {
    const date = new Date(y, m, d);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function getEventsForDate(dateStr: string): CalendarEvent[] {
    return props.events.filter((e) => e.date === dateStr);
}

function goToToday() {
    const n = new Date();
    currentYear.value = n.getFullYear();
    currentMonth.value = n.getMonth();
}

function prevMonth() {
    if (currentMonth.value === 0) {
        currentMonth.value = 11;
        currentYear.value--;
    } else {
        currentMonth.value--;
    }
}

function nextMonth() {
    if (currentMonth.value === 11) {
        currentMonth.value = 0;
        currentYear.value++;
    } else {
        currentMonth.value++;
    }
}

function selectDate(date: string) {
    selectedDate.value = selectedDate.value === date ? null : date;
}

watch([currentYear, currentMonth], () => {
    emit('monthChange', currentYear.value, currentMonth.value + 1);
}, { immediate: true });

const typeLabels: Record<string, { label: string; color: string }> = {
    exam: { label: 'Ujian', color: 'bg-red-500' },
    assignment: { label: 'Deadline Tugas', color: 'bg-yellow-500' },
    attendance: { label: 'Presensi', color: 'bg-blue-500' },
};
</script>

<template>
    <div>
        <MonthNavigator :month-label="monthLabel" @prev="prevMonth" @next="nextMonth" @today="goToToday" />

        <!-- Legend -->
        <div class="flex gap-4 mb-3 text-sm">
            <div v-for="(info, type) in typeLabels" :key="type" class="flex items-center gap-1.5">
                <div class="size-2.5 rounded-full" :class="info.color" />
                <span class="text-muted-foreground">{{ info.label }}</span>
            </div>
        </div>

        <!-- Day Headers -->
        <div class="grid grid-cols-7 gap-1 mb-1">
            <div v-for="name in dayNames" :key="name" class="text-center text-xs font-medium text-muted-foreground py-1">
                {{ name }}
            </div>
        </div>

        <!-- Calendar Grid -->
        <div class="grid grid-cols-7 gap-1">
            <CalendarDay
                v-for="(cell, idx) in days"
                :key="idx"
                :day="cell.day"
                :date="cell.date"
                :is-current-month="cell.isCurrentMonth"
                :is-today="cell.isToday"
                :events="cell.events"
                @select="selectDate"
            />
        </div>

        <!-- Selected Date Events -->
        <div v-if="selectedDate && selectedEvents.length" class="mt-4 rounded-lg border p-4">
            <h3 class="font-semibold mb-2">
                {{ new Date(selectedDate + 'T00:00:00').toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }}
            </h3>
            <div class="space-y-2">
                <div v-for="event in selectedEvents" :key="`${event.type}-${event.id}`" class="flex items-center gap-2 text-sm">
                    <div class="size-2.5 rounded-full" :class="typeLabels[event.type]?.color ?? 'bg-gray-500'" />
                    <Badge :variant="event.type === 'exam' ? 'destructive' : event.type === 'assignment' ? 'default' : 'secondary'" class="text-xs">
                        {{ typeLabels[event.type]?.label ?? event.type }}
                    </Badge>
                    <span>{{ event.title }}</span>
                    <span v-if="event.subject" class="text-muted-foreground">({{ event.subject }})</span>
                </div>
            </div>
        </div>
    </div>
</template>
