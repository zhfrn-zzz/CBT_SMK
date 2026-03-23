<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';

// F1.1: Lazy-load TiptapEditor — saves ~121 KB gzip for pages that don't use the editor
const TiptapEditor = defineAsyncComponent(() => import('./TiptapEditor.vue'));
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Plus, Trash2 } from 'lucide-vue-next';
import type { QuestionType, QuestionOptionForm, MatchingPairForm } from '@/types';
import { questionTypeLabels } from '@/types/exam';

const props = defineProps<{
    form: {
        type: QuestionType;
        content: string;
        points: number;
        explanation: string;
        options: QuestionOptionForm[];
        keywords: string[];
        matching_pairs: MatchingPairForm[];
        errors: Record<string, string>;
        processing: boolean;
    };
}>();

const emit = defineEmits<{
    submit: [];
}>();

const allTypes: QuestionType[] = [
    'pilihan_ganda',
    'benar_salah',
    'esai',
    'isian_singkat',
    'menjodohkan',
    'ordering',
    'multiple_answer',
];

const hasOptions = computed(() =>
    ['pilihan_ganda', 'benar_salah', 'multiple_answer', 'ordering'].includes(props.form.type),
);

const canAddOption = computed(() =>
    ['pilihan_ganda', 'multiple_answer', 'ordering'].includes(props.form.type),
);

function onTypeChange(value: string) {
    props.form.type = value as QuestionType;

    // Reset all type-specific data
    props.form.options = [];
    props.form.keywords = [];
    props.form.matching_pairs = [];

    if (value === 'benar_salah') {
        props.form.options = [
            { label: 'A', content: 'Benar', is_correct: false },
            { label: 'B', content: 'Salah', is_correct: false },
        ];
    } else if (value === 'pilihan_ganda') {
        props.form.options = [
            { label: 'A', content: '', is_correct: false },
            { label: 'B', content: '', is_correct: false },
            { label: 'C', content: '', is_correct: false },
            { label: 'D', content: '', is_correct: false },
        ];
    } else if (value === 'multiple_answer') {
        props.form.options = [
            { label: 'A', content: '', is_correct: false },
            { label: 'B', content: '', is_correct: false },
            { label: 'C', content: '', is_correct: false },
            { label: 'D', content: '', is_correct: false },
        ];
    } else if (value === 'ordering') {
        props.form.options = [
            { label: '1', content: '', is_correct: true },
            { label: '2', content: '', is_correct: true },
            { label: '3', content: '', is_correct: true },
        ];
    } else if (value === 'isian_singkat') {
        props.form.keywords = [''];
    } else if (value === 'menjodohkan') {
        props.form.matching_pairs = [
            { premise: '', response: '' },
            { premise: '', response: '' },
        ];
    }
}

function addOption() {
    const labels = 'ABCDEFGHIJ';
    if (props.form.type === 'ordering') {
        const nextLabel = String(props.form.options.length + 1);
        props.form.options.push({ label: nextLabel, content: '', is_correct: true });
    } else {
        const nextLabel = labels[props.form.options.length] ?? String(props.form.options.length + 1);
        props.form.options.push({ label: nextLabel, content: '', is_correct: false });
    }
}

function removeOption(index: number) {
    props.form.options.splice(index, 1);
    // Re-label
    if (props.form.type === 'ordering') {
        props.form.options.forEach((opt, i) => {
            opt.label = String(i + 1);
        });
    } else {
        const labels = 'ABCDEFGHIJ';
        props.form.options.forEach((opt, i) => {
            opt.label = labels[i] ?? String(i + 1);
        });
    }
}

function setCorrectAnswer(index: number) {
    if (props.form.type === 'pilihan_ganda' || props.form.type === 'benar_salah') {
        // Single correct answer
        props.form.options.forEach((opt, i) => {
            opt.is_correct = i === index;
        });
    } else {
        // Multiple answer toggle
        props.form.options[index].is_correct = !props.form.options[index].is_correct;
    }
}

// Keywords (Isian Singkat)
function addKeyword() {
    props.form.keywords.push('');
}

function removeKeyword(index: number) {
    props.form.keywords.splice(index, 1);
}

// Matching Pairs (Menjodohkan)
function addMatchingPair() {
    props.form.matching_pairs.push({ premise: '', response: '' });
}

function removeMatchingPair(index: number) {
    props.form.matching_pairs.splice(index, 1);
}
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-6">
        <!-- Question Type -->
        <div class="space-y-2">
            <Label>Tipe Soal</Label>
            <Select :model-value="form.type" @update:model-value="(v: any) => onTypeChange(String(v))">
                <SelectTrigger class="w-[250px]">
                    <SelectValue placeholder="Pilih tipe soal" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="type in allTypes"
                        :key="type"
                        :value="type"
                    >
                        {{ questionTypeLabels[type] }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="form.errors.type" />
        </div>

        <!-- Question Content (Tiptap) -->
        <div class="space-y-2">
            <Label>Konten Soal</Label>
            <TiptapEditor v-model="form.content" />
            <InputError :message="form.errors.content" />
        </div>

        <!-- Points -->
        <div class="space-y-2">
            <Label for="points">Bobot Nilai</Label>
            <Input
                id="points"
                v-model.number="form.points"
                type="number"
                step="0.01"
                min="0.01"
                class="max-w-[150px]"
            />
            <InputError :message="form.errors.points" />
        </div>

        <!-- Options (for PG, B/S, Multiple Answer, Ordering) -->
        <div v-if="hasOptions" class="space-y-3">
            <div class="flex items-center justify-between">
                <Label>
                    {{ form.type === 'ordering' ? 'Item Urutan (urutan benar dari atas ke bawah)' : 'Pilihan Jawaban' }}
                </Label>
                <Button
                    v-if="canAddOption"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="addOption"
                    :disabled="form.options.length >= (form.type === 'ordering' ? 10 : 5)"
                >
                    <Plus class="size-4" />
                    {{ form.type === 'ordering' ? 'Tambah Item' : 'Tambah Opsi' }}
                </Button>
            </div>

            <InputError :message="form.errors.options" />

            <div
                v-for="(option, index) in form.options"
                :key="index"
                class="flex items-start gap-3 rounded-md border p-3"
                :class="option.is_correct && form.type !== 'ordering' ? 'border-green-500 bg-green-50 dark:bg-green-950/20' : ''"
            >
                <!-- For ordering, show order number -->
                <template v-if="form.type === 'ordering'">
                    <span class="mt-1 flex size-8 shrink-0 items-center justify-center rounded-full border-2 border-muted-foreground/30 text-sm font-medium">
                        {{ index + 1 }}
                    </span>
                </template>
                <!-- For other types, clickable correct answer selector -->
                <template v-else>
                    <button
                        type="button"
                        class="mt-1 flex size-8 shrink-0 items-center justify-center rounded-full border-2 text-sm font-medium transition-colors"
                        :class="option.is_correct
                            ? 'border-green-500 bg-green-500 text-white'
                            : 'border-muted-foreground/30 hover:border-primary'
                        "
                        @click="setCorrectAnswer(index)"
                        :title="option.is_correct ? 'Jawaban benar' : 'Tandai sebagai jawaban benar'"
                    >
                        {{ option.label }}
                    </button>
                </template>

                <div class="flex-1">
                    <Input
                        v-model="option.content"
                        :placeholder="form.type === 'ordering' ? `Item urutan ke-${index + 1}` : `Isi pilihan ${option.label}`"
                        :disabled="form.type === 'benar_salah'"
                    />
                    <InputError :message="(form.errors as Record<string, string>)[`options.${index}.content`]" />
                </div>

                <Button
                    v-if="canAddOption && form.options.length > 2"
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    @click="removeOption(index)"
                >
                    <Trash2 class="size-4 text-destructive" />
                </Button>
            </div>

            <p class="text-xs text-muted-foreground">
                <template v-if="form.type === 'ordering'">
                    Masukkan item dalam urutan yang benar. Saat ujian, urutan akan diacak.
                </template>
                <template v-else-if="form.type === 'multiple_answer'">
                    Klik huruf pada opsi untuk menandai jawaban yang benar. Bisa lebih dari satu.
                </template>
                <template v-else>
                    Klik huruf pada opsi untuk menandai jawaban yang benar.
                </template>
            </p>
        </div>

        <!-- Keywords (Isian Singkat) -->
        <div v-if="form.type === 'isian_singkat'" class="space-y-3">
            <div class="flex items-center justify-between">
                <Label>Kata Kunci Jawaban</Label>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="addKeyword"
                    :disabled="form.keywords.length >= 10"
                >
                    <Plus class="size-4" />
                    Tambah Kata Kunci
                </Button>
            </div>

            <InputError :message="form.errors.keywords" />

            <div
                v-for="(_, index) in form.keywords"
                :key="index"
                class="flex items-center gap-3"
            >
                <span class="w-6 text-center text-sm text-muted-foreground">{{ index + 1 }}.</span>
                <Input
                    v-model="form.keywords[index]"
                    :placeholder="`Kata kunci alternatif ${index + 1}`"
                    class="flex-1"
                />
                <Button
                    v-if="form.keywords.length > 1"
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    @click="removeKeyword(index)"
                >
                    <Trash2 class="size-4 text-destructive" />
                </Button>
                <InputError :message="(form.errors as Record<string, string>)[`keywords.${index}`]" />
            </div>

            <p class="text-xs text-muted-foreground">
                Masukkan kata kunci jawaban yang dianggap benar. Pencocokan tidak membedakan huruf besar/kecil.
                Tambahkan variasi jawaban yang mungkin (misal: "fotosintesis", "photo synthesis").
            </p>
        </div>

        <!-- Matching Pairs (Menjodohkan) -->
        <div v-if="form.type === 'menjodohkan'" class="space-y-3">
            <div class="flex items-center justify-between">
                <Label>Pasangan Soal</Label>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="addMatchingPair"
                    :disabled="form.matching_pairs.length >= 10"
                >
                    <Plus class="size-4" />
                    Tambah Pasangan
                </Button>
            </div>

            <InputError :message="form.errors.matching_pairs" />

            <div class="space-y-2">
                <div class="grid grid-cols-[auto_1fr_1fr_auto] gap-2 text-sm font-medium text-muted-foreground">
                    <span class="w-6"></span>
                    <span>Pernyataan (Premise)</span>
                    <span>Jawaban (Response)</span>
                    <span class="w-8"></span>
                </div>
                <div
                    v-for="(pair, index) in form.matching_pairs"
                    :key="index"
                    class="grid grid-cols-[auto_1fr_1fr_auto] items-start gap-2"
                >
                    <span class="mt-2.5 w-6 text-center text-sm text-muted-foreground">{{ index + 1 }}.</span>
                    <div>
                        <Input
                            v-model="pair.premise"
                            :placeholder="`Pernyataan ${index + 1}`"
                        />
                        <InputError :message="(form.errors as Record<string, string>)[`matching_pairs.${index}.premise`]" />
                    </div>
                    <div>
                        <Input
                            v-model="pair.response"
                            :placeholder="`Jawaban ${index + 1}`"
                        />
                        <InputError :message="(form.errors as Record<string, string>)[`matching_pairs.${index}.response`]" />
                    </div>
                    <Button
                        v-if="form.matching_pairs.length > 2"
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        class="mt-1"
                        @click="removeMatchingPair(index)"
                    >
                        <Trash2 class="size-4 text-destructive" />
                    </Button>
                    <span v-else class="w-8"></span>
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                Masukkan pasangan pernyataan dan jawaban yang benar. Saat ujian, jawaban akan diacak.
            </p>
        </div>

        <!-- Explanation -->
        <div class="space-y-2">
            <Label for="explanation">Pembahasan (opsional)</Label>
            <Textarea
                id="explanation"
                v-model="form.explanation"
                placeholder="Pembahasan yang akan ditampilkan setelah ujian selesai"
                rows="3"
            />
            <InputError :message="form.errors.explanation" />
        </div>

        <slot name="actions" />
    </form>
</template>
