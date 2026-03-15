<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ChevronLeft, ChevronRight, Check, Save } from 'lucide-vue-next';
import type { BreadcrumbItem, GradingAnswer, OtherAttempt } from '@/types';

const props = defineProps<{
    examSession: {
        id: number;
        name: string;
        subject: string;
    };
    attempt: {
        id: number;
        user: { id: number; name: string; username: string };
        score: number | null;
        is_fully_graded: boolean;
    };
    answers: GradingAnswer[];
    otherAttempts: OtherAttempt[];
    gradingProgress: { graded: number; total: number };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Penilaian', href: '/guru/grading' },
    { title: props.examSession.name, href: `/guru/grading/${props.examSession.id}` },
    { title: props.attempt.user.name, href: '#' },
];

const currentIndex = ref(0);
const currentAnswer = computed(() => props.answers[currentIndex.value]);

// Per-answer form states
const gradeInputs = ref<Record<number, { score: string; feedback: string }>>(
    Object.fromEntries(
        props.answers.map((a) => [
            a.id,
            {
                score: a.score !== null ? String(a.score) : '',
                feedback: a.feedback ?? '',
            },
        ]),
    ),
);

const progressPercent = computed(() =>
    props.gradingProgress.total > 0
        ? Math.round((props.gradingProgress.graded / props.gradingProgress.total) * 100)
        : 0,
);

function saveGrade(answerId: number) {
    const input = gradeInputs.value[answerId];
    router.post(
        `/guru/grading/${props.examSession.id}/answer/${answerId}`,
        {
            score: parseFloat(input.score) || 0,
            feedback: input.feedback || null,
        },
        { preserveScroll: true },
    );
}

function goToQuestion(index: number) {
    if (index >= 0 && index < props.answers.length) {
        currentIndex.value = index;
    }
}

function navigateToAttempt(attemptId: unknown) {
    router.get(`/guru/grading/${props.examSession.id}/attempt/${attemptId}`);
}

function questionTypeColor(type: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (type === 'esai' || type === 'isian_singkat') return 'destructive';
    if (type === 'pilihan_ganda' || type === 'benar_salah') return 'secondary';
    return 'outline';
}
</script>

<template>
    <Head :title="`Penilaian - ${attempt.user.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />

            <!-- Header with student info & navigation -->
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-xl font-semibold">{{ attempt.user.name }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ attempt.user.username }} &mdash; {{ examSession.name }} ({{ examSession.subject }})
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-muted-foreground">Nilai saat ini:</span>
                    <span class="text-lg font-bold">
                        {{ attempt.score !== null ? attempt.score.toFixed(2) : '-' }}
                    </span>
                    <Badge :variant="attempt.is_fully_graded ? 'default' : 'secondary'">
                        {{ attempt.is_fully_graded ? 'Lengkap' : 'Belum Lengkap' }}
                    </Badge>
                </div>
            </div>

            <!-- Student switcher -->
            <div class="flex items-center gap-2">
                <Label class="text-sm">Pindah siswa:</Label>
                <Select :model-value="String(attempt.id)" @update:model-value="navigateToAttempt">
                    <SelectTrigger class="w-64">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="other in otherAttempts" :key="other.id" :value="String(other.id)">
                            {{ other.user_name }}
                            <span v-if="other.is_fully_graded" class="ml-1 text-green-600">(selesai)</span>
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Progress -->
            <div class="flex items-center gap-4">
                <Progress :model-value="progressPercent" class="flex-1" />
                <span class="text-sm">{{ gradingProgress.graded }}/{{ gradingProgress.total }} dinilai</span>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                <!-- Question Navigation Sidebar -->
                <div class="lg:col-span-1">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm">Navigasi Soal</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-5 gap-2">
                                <button
                                    v-for="(answer, i) in answers"
                                    :key="answer.id"
                                    class="flex size-10 items-center justify-center rounded-md border text-sm font-medium transition-colors"
                                    :class="{
                                        'bg-primary text-primary-foreground': i === currentIndex,
                                        'bg-green-100 border-green-500 dark:bg-green-900/30': answer.score !== null && i !== currentIndex,
                                        'bg-red-100 border-red-300 dark:bg-red-900/30': answer.needs_grading && i !== currentIndex,
                                        'hover:bg-accent': i !== currentIndex,
                                    }"
                                    @click="goToQuestion(i)"
                                >
                                    {{ i + 1 }}
                                </button>
                            </div>
                            <div class="mt-4 space-y-1 text-xs text-muted-foreground">
                                <div class="flex items-center gap-2">
                                    <div class="size-3 rounded bg-green-100 border border-green-500"></div>
                                    <span>Sudah dinilai</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="size-3 rounded bg-red-100 border border-red-300"></div>
                                    <span>Perlu dinilai</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Question Content & Grading -->
                <div class="lg:col-span-3 space-y-4">
                    <Card v-if="currentAnswer">
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm">
                                    Soal {{ currentIndex + 1 }} dari {{ answers.length }}
                                </CardTitle>
                                <div class="flex items-center gap-2">
                                    <Badge :variant="questionTypeColor(currentAnswer.question.type)">
                                        {{ currentAnswer.question.type_label }}
                                    </Badge>
                                    <Badge variant="outline">{{ currentAnswer.question.points }} poin</Badge>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Question content -->
                            <div class="prose prose-sm dark:prose-invert max-w-none" v-html="currentAnswer.question.content"></div>

                            <img v-if="currentAnswer.question.media_url" :src="currentAnswer.question.media_url" alt="Media soal" class="max-h-64 rounded-lg" />

                            <!-- Options (for PG/BS) -->
                            <div v-if="currentAnswer.question.options.length > 0" class="space-y-2">
                                <p class="text-sm font-medium text-muted-foreground">Pilihan jawaban:</p>
                                <div
                                    v-for="opt in currentAnswer.question.options"
                                    :key="opt.label"
                                    class="flex items-start gap-2 rounded-md border p-3"
                                    :class="{
                                        'border-green-500 bg-green-50 dark:bg-green-900/20': opt.is_correct,
                                        'border-red-400 bg-red-50 dark:bg-red-900/20': !opt.is_correct && currentAnswer.answer === opt.label,
                                    }"
                                >
                                    <span class="font-medium min-w-6">{{ opt.label }}.</span>
                                    <span v-html="opt.content"></span>
                                    <Badge v-if="opt.is_correct" variant="default" class="ml-auto shrink-0">Benar</Badge>
                                    <Badge v-if="!opt.is_correct && currentAnswer.answer === opt.label" variant="destructive" class="ml-auto shrink-0">Dipilih</Badge>
                                </div>
                            </div>

                            <Separator />

                            <!-- Student Answer -->
                            <div>
                                <p class="text-sm font-medium text-muted-foreground mb-1">Jawaban siswa:</p>
                                <div v-if="currentAnswer.answer" class="rounded-md border bg-muted/50 p-3">
                                    <div v-if="currentAnswer.question.type === 'esai'" class="prose prose-sm dark:prose-invert max-w-none" v-html="currentAnswer.answer"></div>
                                    <p v-else>{{ currentAnswer.answer }}</p>
                                </div>
                                <p v-else class="text-sm text-muted-foreground italic">Tidak dijawab</p>
                            </div>

                            <!-- Explanation -->
                            <div v-if="currentAnswer.question.explanation">
                                <p class="text-sm font-medium text-muted-foreground mb-1">Pembahasan:</p>
                                <div class="rounded-md border bg-blue-50 dark:bg-blue-900/20 p-3 prose prose-sm dark:prose-invert max-w-none" v-html="currentAnswer.question.explanation"></div>
                            </div>

                            <Separator />

                            <!-- Grading Input -->
                            <div class="space-y-3">
                                <div class="flex items-end gap-4">
                                    <div class="w-32">
                                        <Label>Nilai (maks {{ currentAnswer.question.points }})</Label>
                                        <Input
                                            v-model="gradeInputs[currentAnswer.id].score"
                                            type="number"
                                            :min="0"
                                            :max="currentAnswer.question.points"
                                            :step="0.5"
                                            placeholder="0"
                                        />
                                    </div>
                                    <Button @click="saveGrade(currentAnswer.id)" size="sm">
                                        <Save class="mr-1 size-4" />
                                        Simpan Nilai
                                    </Button>
                                </div>
                                <div>
                                    <Label>Feedback (opsional)</Label>
                                    <Textarea
                                        v-model="gradeInputs[currentAnswer.id].feedback"
                                        placeholder="Tulis komentar untuk siswa..."
                                        rows="2"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Navigation Buttons -->
                    <div class="flex justify-between">
                        <Button variant="outline" :disabled="currentIndex === 0" @click="goToQuestion(currentIndex - 1)">
                            <ChevronLeft class="mr-1 size-4" />
                            Sebelumnya
                        </Button>
                        <Button variant="outline" :disabled="currentIndex === answers.length - 1" @click="goToQuestion(currentIndex + 1)">
                            Selanjutnya
                            <ChevronRight class="ml-1 size-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
