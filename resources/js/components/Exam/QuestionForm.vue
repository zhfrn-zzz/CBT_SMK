<script setup lang="ts">
import { computed } from 'vue';
import TiptapEditor from './TiptapEditor.vue';
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
import type { QuestionType, QuestionOptionForm } from '@/types';
import { questionTypeLabels } from '@/types/exam';

const props = defineProps<{
    form: {
        type: QuestionType;
        content: string;
        points: number;
        explanation: string;
        options: QuestionOptionForm[];
        errors: Record<string, string>;
        processing: boolean;
    };
}>();

const emit = defineEmits<{
    submit: [];
}>();

const phase1Types: QuestionType[] = ['pilihan_ganda', 'benar_salah', 'esai'];

const hasOptions = computed(() =>
    ['pilihan_ganda', 'benar_salah', 'multiple_answer'].includes(props.form.type),
);

function onTypeChange(value: string) {
    props.form.type = value as QuestionType;

    // Reset options based on type
    if (value === 'benar_salah') {
        props.form.options = [
            { label: 'A', content: 'Benar', is_correct: false },
            { label: 'B', content: 'Salah', is_correct: false },
        ];
    } else if (value === 'pilihan_ganda') {
        if (props.form.options.length < 2) {
            props.form.options = [
                { label: 'A', content: '', is_correct: false },
                { label: 'B', content: '', is_correct: false },
                { label: 'C', content: '', is_correct: false },
                { label: 'D', content: '', is_correct: false },
            ];
        }
    } else {
        props.form.options = [];
    }
}

function addOption() {
    const labels = 'ABCDEFGHIJ';
    const nextLabel = labels[props.form.options.length] ?? String(props.form.options.length + 1);
    props.form.options.push({ label: nextLabel, content: '', is_correct: false });
}

function removeOption(index: number) {
    props.form.options.splice(index, 1);
    // Re-label
    const labels = 'ABCDEFGHIJ';
    props.form.options.forEach((opt, i) => {
        opt.label = labels[i] ?? String(i + 1);
    });
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
                        v-for="type in phase1Types"
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

        <!-- Options (for PG, B/S, Multiple Answer) -->
        <div v-if="hasOptions" class="space-y-3">
            <div class="flex items-center justify-between">
                <Label>Pilihan Jawaban</Label>
                <Button
                    v-if="form.type === 'pilihan_ganda'"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="addOption"
                    :disabled="form.options.length >= 5"
                >
                    <Plus class="size-4" />
                    Tambah Opsi
                </Button>
            </div>

            <InputError :message="form.errors.options" />

            <div
                v-for="(option, index) in form.options"
                :key="index"
                class="flex items-start gap-3 rounded-md border p-3"
                :class="option.is_correct ? 'border-green-500 bg-green-50 dark:bg-green-950/20' : ''"
            >
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

                <div class="flex-1">
                    <Input
                        v-model="option.content"
                        :placeholder="`Isi pilihan ${option.label}`"
                        :disabled="form.type === 'benar_salah'"
                    />
                    <InputError :message="(form.errors as Record<string, string>)[`options.${index}.content`]" />
                </div>

                <Button
                    v-if="form.type === 'pilihan_ganda' && form.options.length > 2"
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    @click="removeOption(index)"
                >
                    <Trash2 class="size-4 text-destructive" />
                </Button>
            </div>

            <p class="text-xs text-muted-foreground">
                Klik huruf pada opsi untuk menandai jawaban yang benar.
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
