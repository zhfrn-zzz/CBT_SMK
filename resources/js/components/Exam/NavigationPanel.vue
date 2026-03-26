<script setup lang="ts">
import { computed } from 'vue';
import type { ExamQuestion } from '@/types';

const props = defineProps<{
    questions: ExamQuestion[];
    currentIndex: number;
    getStatus: (questionId: number) => 'answered' | 'flagged' | 'unanswered' | 'current';
    answeredCount: number;
    flaggedCount: number;
    unansweredCount: number;
    totalQuestions: number;
}>();

defineEmits<{
    'go-to': [index: number];
}>();

function statusClass(questionId: number, index: number): string {
    const isCurrent = index === props.currentIndex;
    const currentRing = isCurrent ? ' ring-2 ring-primary ring-offset-2' : '';

    if (isCurrent) {
        const status = props.getStatus(questionId);
        if (status === 'answered') return 'bg-primary text-primary-foreground' + currentRing;
        if (status === 'flagged') return 'bg-amber-400 text-white' + currentRing;
        return 'bg-slate-100 text-slate-600 border border-slate-200' + currentRing;
    }

    const status = props.getStatus(questionId);
    switch (status) {
        case 'answered':
            return 'bg-primary text-primary-foreground';
        case 'flagged':
            return 'bg-amber-400 text-white';
        case 'unanswered':
        default:
            return 'bg-slate-100 text-slate-500 border border-slate-200 hover:bg-slate-200';
    }
}
</script>

<template>
    <div class="space-y-4">
        <h3 class="text-sm font-semibold text-foreground">Navigasi Soal</h3>

        <!-- Question Grid -->
        <div class="grid grid-cols-6 gap-1.5 sm:grid-cols-5 sm:gap-2">
            <button
                v-for="(q, i) in questions"
                :key="q.id"
                type="button"
                class="flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-lg text-sm font-semibold transition-all"
                :class="statusClass(q.id, i)"
                @click="$emit('go-to', i)"
            >
                {{ q.order }}
            </button>
        </div>

        <!-- Legend -->
        <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-muted-foreground">
            <div class="flex items-center gap-1.5">
                <span class="inline-block size-3 rounded-sm bg-slate-100 border border-slate-200" />
                Belum dijawab
            </div>
            <div class="flex items-center gap-1.5">
                <span class="inline-block size-3 rounded-sm bg-primary" />
                Sudah dijawab
            </div>
            <div class="flex items-center gap-1.5">
                <span class="inline-block size-3 rounded-sm bg-amber-400" />
                Ditandai
            </div>
        </div>

        <!-- Summary -->
        <div class="rounded-lg bg-slate-50 p-3 space-y-1.5 text-sm">
            <div class="flex justify-between">
                <span class="text-muted-foreground">Dijawab</span>
                <span class="font-semibold">{{ answeredCount }}/{{ totalQuestions }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-muted-foreground">Ditandai</span>
                <span class="font-semibold text-amber-600">{{ flaggedCount }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-muted-foreground">Belum</span>
                <span class="font-semibold text-red-500">{{ unansweredCount }}</span>
            </div>
        </div>
    </div>
</template>
