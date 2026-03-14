<script setup lang="ts">
import type { ExamQuestion } from '@/types';

const props = defineProps<{
    questions: ExamQuestion[];
    currentIndex: number;
    getStatus: (questionId: number) => 'answered' | 'flagged' | 'unanswered' | 'current';
}>();

const emit = defineEmits<{
    'go-to': [index: number];
}>();

function statusClass(questionId: number, index: number): string {
    if (index === props.currentIndex) {
        return 'bg-primary text-primary-foreground ring-2 ring-primary ring-offset-2';
    }

    const status = props.getStatus(questionId);
    switch (status) {
        case 'answered':
            return 'bg-green-500 text-white';
        case 'flagged':
            return 'bg-yellow-400 text-yellow-900';
        case 'unanswered':
        default:
            return 'bg-muted text-muted-foreground hover:bg-muted/80';
    }
}
</script>

<template>
    <div class="space-y-3">
        <h3 class="text-sm font-semibold">Navigasi Soal</h3>
        <div class="grid grid-cols-5 gap-2">
            <button
                v-for="(q, i) in questions"
                :key="q.id"
                type="button"
                class="flex size-9 items-center justify-center rounded-md text-sm font-medium transition-all"
                :class="statusClass(q.id, i)"
                @click="$emit('go-to', i)"
            >
                {{ q.order }}
            </button>
        </div>
        <div class="space-y-1 text-xs text-muted-foreground">
            <div class="flex items-center gap-2">
                <span class="inline-block size-3 rounded-sm bg-primary" />
                Soal saat ini
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-block size-3 rounded-sm bg-green-500" />
                Sudah dijawab
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-block size-3 rounded-sm bg-yellow-400" />
                Ditandai (ragu)
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-block size-3 rounded-sm bg-muted" />
                Belum dijawab
            </div>
        </div>
    </div>
</template>
