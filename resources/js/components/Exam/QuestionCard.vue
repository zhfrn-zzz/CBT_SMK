<script setup lang="ts">
import { computed } from 'vue';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import type { ExamQuestion } from '@/types';

const props = defineProps<{
    question: ExamQuestion;
    answer: string | undefined;
    isFlagged: boolean;
}>();

const emit = defineEmits<{
    'update:answer': [answer: string];
    'toggle-flag': [];
}>();

const currentAnswer = computed({
    get: () => props.answer ?? '',
    set: (val: string) => emit('update:answer', val),
});

function selectOption(label: string) {
    emit('update:answer', label);
}

const typeLabel = computed(() => {
    const labels: Record<string, string> = {
        pilihan_ganda: 'Pilihan Ganda',
        benar_salah: 'Benar/Salah',
        esai: 'Esai',
        isian_singkat: 'Isian Singkat',
        multiple_answer: 'Pilihan Ganda Kompleks',
    };
    return labels[props.question.type] ?? props.question.type;
});
</script>

<template>
    <div class="space-y-4">
        <!-- Question Header -->
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-2">
                <span class="flex size-8 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground">
                    {{ question.order }}
                </span>
                <Badge variant="outline" class="text-xs">{{ typeLabel }}</Badge>
                <Badge v-if="question.points !== 1" variant="secondary" class="text-xs">
                    {{ question.points }} poin
                </Badge>
            </div>
            <button
                type="button"
                class="rounded-md px-2 py-1 text-sm transition-colors"
                :class="isFlagged
                    ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                    : 'text-muted-foreground hover:bg-muted'"
                @click="$emit('toggle-flag')"
            >
                {{ isFlagged ? 'Ditandai' : 'Tandai' }}
            </button>
        </div>

        <!-- Question Content (rich text) -->
        <div class="prose prose-sm dark:prose-invert max-w-none" v-html="question.content" />

        <!-- Media -->
        <img
            v-if="question.media_url"
            :src="question.media_url"
            alt="Media soal"
            class="max-h-64 rounded-md"
        />

        <!-- Answer Section -->
        <div class="space-y-2">
            <!-- Pilihan Ganda / Benar Salah -->
            <template v-if="question.type === 'pilihan_ganda' || question.type === 'benar_salah'">
                <button
                    v-for="option in question.options"
                    :key="option.id"
                    type="button"
                    class="flex w-full items-start gap-3 rounded-lg border p-3 text-left transition-colors"
                    :class="currentAnswer === option.label
                        ? 'border-primary bg-primary/5 ring-1 ring-primary'
                        : 'hover:bg-muted/50'"
                    @click="selectOption(option.label)"
                >
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-full border text-sm font-medium"
                        :class="currentAnswer === option.label
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-muted-foreground/30'"
                    >
                        {{ option.label }}
                    </span>
                    <span class="pt-0.5" v-html="option.content" />
                </button>
            </template>

            <!-- Esai -->
            <template v-else-if="question.type === 'esai'">
                <Label class="text-sm text-muted-foreground">Jawaban Anda:</Label>
                <Textarea
                    v-model="currentAnswer"
                    placeholder="Tulis jawaban Anda di sini..."
                    :rows="8"
                    class="resize-y"
                />
            </template>

            <!-- Isian Singkat -->
            <template v-else-if="question.type === 'isian_singkat'">
                <Label class="text-sm text-muted-foreground">Jawaban Anda:</Label>
                <input
                    v-model="currentAnswer"
                    type="text"
                    placeholder="Tulis jawaban singkat..."
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                />
            </template>
        </div>
    </div>
</template>
