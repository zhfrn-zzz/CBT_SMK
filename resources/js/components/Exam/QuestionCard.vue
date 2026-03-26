<script setup lang="ts">
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    RadioGroup,
    RadioGroupItem,
} from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import { GripVertical } from 'lucide-vue-next';
import type { ExamQuestion } from '@/types';

const props = defineProps<{
    question: ExamQuestion;
    answer: string | undefined;
    isFlagged: boolean;
    questionNumber: number;
    totalQuestions: number;
}>();

const emit = defineEmits<{
    'update:answer': [answer: string];
    'toggle-flag': [];
}>();

const currentAnswer = computed({
    get: () => props.answer ?? '',
    set: (val: string) => emit('update:answer', val),
});

// --- Pilihan Ganda ---
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

// Drag-and-drop
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

// --- Esai word count ---
const wordCount = computed(() => {
    const text = currentAnswer.value.trim();
    if (!text) return 0;
    return text.split(/\s+/).length;
});

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
    <div class="bg-white rounded-lg border p-4 sm:p-6 md:p-8">
        <!-- Question Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-muted-foreground">
                    Soal {{ questionNumber }} / {{ totalQuestions }}
                </span>
            </div>
            <Badge variant="outline" class="text-xs">{{ typeLabel }}</Badge>
        </div>

        <Separator class="my-4" />

        <!-- Question Content -->
        <div class="prose prose-base sm:prose-lg dark:prose-invert max-w-none leading-relaxed" v-html="question.content" />

        <!-- Media -->
        <img
            v-if="question.media_url"
            :src="question.media_url"
            alt="Media soal"
            loading="lazy"
            class="max-w-full rounded-lg border mt-4 mb-6"
        />

        <!-- Answer Section -->
        <div class="mt-6 space-y-3">
            <!-- Pilihan Ganda (PG) — Radio buttons -->
            <template v-if="question.type === 'pilihan_ganda'">
                <RadioGroup :model-value="currentAnswer" @update:model-value="(v: string) => selectOption(v)">
                    <div class="space-y-3">
                        <label
                            v-for="option in question.options"
                            :key="option.id"
                            class="flex w-full cursor-pointer items-center gap-3 sm:gap-4 rounded-lg border p-3 sm:p-4 transition-all min-h-[48px]"
                            :class="currentAnswer === option.label
                                ? 'bg-primary/5 border-primary ring-2 ring-primary/20'
                                : 'hover:bg-slate-50 hover:border-primary/30'"
                        >
                            <RadioGroupItem :value="option.label" class="h-5 w-5 shrink-0" />
                            <span class="text-sm font-bold text-muted-foreground">{{ option.label }}.</span>
                            <span class="flex-1" v-html="option.content" />
                        </label>
                    </div>
                </RadioGroup>
            </template>

            <!-- Benar / Salah (B/S) — Two big buttons -->
            <template v-else-if="question.type === 'benar_salah'">
                <div class="grid grid-cols-2 gap-3 sm:gap-4">
                    <button
                        v-for="option in question.options"
                        :key="option.id"
                        type="button"
                        class="h-14 sm:h-16 rounded-lg border-2 text-base sm:text-lg font-semibold transition-all"
                        :class="option.label === 'A'
                            ? (currentAnswer === option.label
                                ? 'bg-emerald-500 border-emerald-500 text-white shadow-md'
                                : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50')
                            : (currentAnswer === option.label
                                ? 'bg-red-500 border-red-500 text-white shadow-md'
                                : 'border-red-300 text-red-700 hover:bg-red-50')"
                        @click="selectOption(option.label)"
                    >
                        {{ option.content }}
                    </button>
                </div>
            </template>

            <!-- Multiple Answer (Checkbox) -->
            <template v-else-if="question.type === 'multiple_answer'">
                <p class="text-sm italic text-muted-foreground mb-3">Pilih semua jawaban yang benar</p>
                <div class="space-y-3">
                    <button
                        v-for="option in question.options"
                        :key="option.id"
                        type="button"
                        class="flex w-full items-center gap-3 sm:gap-4 rounded-lg border p-3 sm:p-4 text-left transition-all min-h-[48px]"
                        :class="isMultipleSelected(option.label)
                            ? 'bg-primary/5 border-primary ring-2 ring-primary/20'
                            : 'hover:bg-slate-50 hover:border-primary/30'"
                        @click="toggleMultipleOption(option.label)"
                    >
                        <Checkbox
                            :checked="isMultipleSelected(option.label)"
                            class="h-5 w-5 shrink-0"
                            @click.stop
                            @update:checked="() => toggleMultipleOption(option.label)"
                        />
                        <span class="text-sm font-bold text-muted-foreground">{{ option.label }}.</span>
                        <span class="flex-1" v-html="option.content" />
                    </button>
                </div>
            </template>

            <!-- Esai -->
            <template v-else-if="question.type === 'esai'">
                <Textarea
                    v-model="currentAnswer"
                    placeholder="Tulis jawaban Anda di sini..."
                    class="min-h-[150px] sm:min-h-[200px] text-base p-4 rounded-lg resize-y"
                />
                <div class="text-right">
                    <span class="text-xs text-muted-foreground">{{ wordCount }} kata</span>
                </div>
            </template>

            <!-- Isian Singkat -->
            <template v-else-if="question.type === 'isian_singkat'">
                <Input
                    v-model="currentAnswer"
                    type="text"
                    placeholder="Ketik jawaban singkat..."
                    class="h-14 text-lg text-center rounded-lg"
                />
            </template>

            <!-- Menjodohkan -->
            <template v-else-if="question.type === 'menjodohkan' && question.matching_premises">
                <p class="text-sm text-muted-foreground mb-3">Cocokkan setiap pernyataan dengan jawaban yang tepat:</p>
                <div class="space-y-3">
                    <div
                        v-for="(premise, idx) in question.matching_premises"
                        :key="premise.id"
                        class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 rounded-lg p-3"
                        :class="getMatchingAnswer(premise.id) ? 'bg-primary/5 border border-primary/30' : 'bg-slate-50 border border-slate-200'"
                    >
                        <div class="flex-1">
                            <span class="text-xs font-medium text-muted-foreground">{{ idx + 1 }}.</span>
                            <span class="ml-1 text-base" v-html="premise.content" />
                        </div>
                        <div class="w-full sm:w-52">
                            <Select
                                :model-value="getMatchingAnswer(premise.id)"
                                @update:model-value="(v: any) => setMatchingAnswer(premise.id, v === '__clear__' ? '' : String(v ?? ''))"
                            >
                                <SelectTrigger class="h-11">
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

            <!-- Ordering (Sortable) -->
            <template v-else-if="question.type === 'ordering'">
                <p class="text-sm text-muted-foreground mb-3">Susun item dalam urutan yang benar:</p>
                <div class="space-y-2">
                    <div
                        v-for="(item, index) in orderingItems"
                        :key="item.id"
                        draggable="true"
                        class="flex items-center gap-2 sm:gap-3 rounded-lg border bg-white p-3 sm:p-4 transition-all touch-manipulation"
                        :class="draggedIndex === index
                            ? 'shadow-lg ring-2 ring-primary/30 opacity-80'
                            : 'cursor-grab hover:bg-muted'"
                        @dragstart="onDragStart(index)"
                        @dragover="onDragOver"
                        @drop="onDrop(index)"
                        @dragend="onDragEnd"
                        @touchstart.passive="onDragStart(index)"
                    >
                        <GripVertical class="size-6 sm:size-5 text-muted-foreground shrink-0 w-10 sm:w-auto" />
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-muted-foreground">
                            {{ index + 1 }}
                        </span>
                        <span class="flex-1 text-base" v-html="item.content" />
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
