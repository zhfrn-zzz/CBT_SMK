<script setup lang="ts">
import { computed, ref } from 'vue';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { ArrowUp, ArrowDown } from 'lucide-vue-next';
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

// --- Pilihan Ganda / Benar Salah ---
function selectOption(label: string) {
    emit('update:answer', label);
}

// --- Multiple Answer (checkbox-style) ---
const selectedMultiple = computed<string[]>({
    get() {
        if (!props.answer) return [];
        try {
            return JSON.parse(props.answer);
        } catch {
            return [];
        }
    },
    set(val: string[]) {
        emit('update:answer', JSON.stringify(val));
    },
});

function toggleMultipleOption(label: string) {
    const current = [...selectedMultiple.value];
    const idx = current.indexOf(label);
    if (idx >= 0) {
        current.splice(idx, 1);
    } else {
        current.push(label);
    }
    current.sort();
    selectedMultiple.value = current;
}

function isMultipleSelected(label: string): boolean {
    return selectedMultiple.value.includes(label);
}

// --- Menjodohkan ---
const matchingAnswers = computed<Record<string, string>>({
    get() {
        if (!props.answer) return {};
        try {
            return JSON.parse(props.answer);
        } catch {
            return {};
        }
    },
    set(val: Record<string, string>) {
        emit('update:answer', JSON.stringify(val));
    },
});

function setMatchingAnswer(premiseId: number, responseId: string) {
    const current = { ...matchingAnswers.value };
    if (responseId === '') {
        delete current[String(premiseId)];
    } else {
        current[String(premiseId)] = responseId;
    }
    matchingAnswers.value = current;
}

function getMatchingAnswer(premiseId: number): string {
    return matchingAnswers.value[String(premiseId)] ?? '';
}

// Check if a response is already used by another premise
function isResponseUsed(responseId: number, excludePremiseId: number): boolean {
    const answers = matchingAnswers.value;
    const responseStr = String(responseId);
    for (const [premiseId, selectedResponse] of Object.entries(answers)) {
        if (Number(premiseId) !== excludePremiseId && selectedResponse === responseStr) {
            return true;
        }
    }
    return false;
}

// --- Ordering ---
const orderingItems = computed<{ id: number; label: string; content: string }[]>({
    get() {
        if (!props.question.options) return [];
        if (!props.answer) {
            // Show shuffled order from server
            return props.question.options.map((opt) => ({
                id: opt.id,
                label: opt.label,
                content: opt.content,
            }));
        }
        try {
            const order: number[] = JSON.parse(props.answer);
            const optMap = new Map(props.question.options.map((o) => [o.id, o]));
            return order
                .map((id) => optMap.get(id))
                .filter(Boolean)
                .map((opt) => ({
                    id: opt!.id,
                    label: opt!.label,
                    content: opt!.content,
                }));
        } catch {
            return props.question.options.map((opt) => ({
                id: opt.id,
                label: opt.label,
                content: opt.content,
            }));
        }
    },
    set(items) {
        emit('update:answer', JSON.stringify(items.map((i) => i.id)));
    },
});

function moveOrderingItem(index: number, direction: 'up' | 'down') {
    const items = [...orderingItems.value];
    const newIndex = direction === 'up' ? index - 1 : index + 1;
    if (newIndex < 0 || newIndex >= items.length) return;
    [items[index], items[newIndex]] = [items[newIndex], items[index]];
    orderingItems.value = items;
}

// Drag-and-drop state
const draggedIndex = ref<number | null>(null);

function onDragStart(index: number) {
    draggedIndex.value = index;
}

function onDragOver(e: DragEvent) {
    e.preventDefault();
}

function onDrop(targetIndex: number) {
    if (draggedIndex.value === null || draggedIndex.value === targetIndex) return;
    const items = [...orderingItems.value];
    const [moved] = items.splice(draggedIndex.value, 1);
    items.splice(targetIndex, 0, moved);
    orderingItems.value = items;
    draggedIndex.value = null;
}

function onDragEnd() {
    draggedIndex.value = null;
}

const typeLabel = computed(() => {
    const labels: Record<string, string> = {
        pilihan_ganda: 'Pilihan Ganda',
        benar_salah: 'Benar/Salah',
        esai: 'Esai',
        isian_singkat: 'Isian Singkat',
        multiple_answer: 'Pilihan Ganda Kompleks',
        menjodohkan: 'Menjodohkan',
        ordering: 'Pengurutan',
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

            <!-- Multiple Answer (Checkbox style) -->
            <template v-else-if="question.type === 'multiple_answer'">
                <p class="text-sm text-muted-foreground">Pilih semua jawaban yang benar:</p>
                <button
                    v-for="option in question.options"
                    :key="option.id"
                    type="button"
                    class="flex w-full items-start gap-3 rounded-lg border p-3 text-left transition-colors"
                    :class="isMultipleSelected(option.label)
                        ? 'border-primary bg-primary/5 ring-1 ring-primary'
                        : 'hover:bg-muted/50'"
                    @click="toggleMultipleOption(option.label)"
                >
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded border text-sm font-medium"
                        :class="isMultipleSelected(option.label)
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-muted-foreground/30'"
                    >
                        <svg v-if="isMultipleSelected(option.label)" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        <template v-else>{{ option.label }}</template>
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

            <!-- Menjodohkan -->
            <template v-else-if="question.type === 'menjodohkan' && question.matching_premises">
                <p class="text-sm text-muted-foreground">Cocokkan setiap pernyataan dengan jawaban yang tepat:</p>
                <div class="space-y-3">
                    <div
                        v-for="premise in question.matching_premises"
                        :key="premise.id"
                        class="flex items-center gap-3 rounded-lg border p-3"
                        :class="getMatchingAnswer(premise.id) ? 'border-primary/50 bg-primary/5' : ''"
                    >
                        <div class="flex-1 text-sm font-medium" v-html="premise.content" />
                        <div class="w-48">
                            <Select
                                :model-value="getMatchingAnswer(premise.id)"
                                @update:model-value="(v: any) => setMatchingAnswer(premise.id, v === '__clear__' ? '' : String(v ?? ''))"
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih jawaban..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__clear__" class="text-muted-foreground">-- Kosongkan --</SelectItem>
                                    <SelectItem
                                        v-for="response in question.matching_responses"
                                        :key="response.id"
                                        :value="String(response.id)"
                                        :disabled="isResponseUsed(response.id, premise.id)"
                                    >
                                        {{ response.content }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Ordering (Drag & Drop + Buttons) -->
            <template v-else-if="question.type === 'ordering'">
                <p class="text-sm text-muted-foreground">Susun item dalam urutan yang benar:</p>
                <div class="space-y-1">
                    <div
                        v-for="(item, index) in orderingItems"
                        :key="item.id"
                        draggable="true"
                        class="flex items-center gap-2 rounded-lg border bg-background p-3 transition-colors"
                        :class="draggedIndex === index ? 'opacity-50' : 'cursor-grab hover:bg-muted/50'"
                        @dragstart="onDragStart(index)"
                        @dragover="onDragOver"
                        @drop="onDrop(index)"
                        @dragend="onDragEnd"
                    >
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-medium text-muted-foreground">
                            {{ index + 1 }}
                        </span>
                        <span class="flex-1 text-sm" v-html="item.content" />
                        <div class="flex flex-col gap-0.5">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-6"
                                :disabled="index === 0"
                                @click="moveOrderingItem(index, 'up')"
                            >
                                <ArrowUp class="size-3" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-6"
                                :disabled="index === orderingItems.length - 1"
                                @click="moveOrderingItem(index, 'down')"
                            >
                                <ArrowDown class="size-3" />
                            </Button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
