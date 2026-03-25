<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatsCard from '@/Components/StatsCard.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Award, BookOpen, CheckCircle, Trophy, XCircle } from 'lucide-vue-next';
import type { BreadcrumbItem, GradingAnswer } from '@/types';

const props = defineProps<{
    attempt: {
        id: number;
        score: number | null;
        is_fully_graded: boolean;
        submitted_at: string | null;
        pass_status: 'lulus' | 'remedial' | null;
    };
    examSession: {
        id: number;
        name: string;
        subject: string;
        kkm: number | null;
    };
    answers: GradingAnswer[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/siswa/dashboard' },
    { title: 'Nilai', href: '/siswa/nilai' },
    { title: props.examSession.name, href: '#' },
];

const totalPoints = computed(() => props.answers.reduce((sum, a) => sum + a.question.points, 0));
const earnedPoints = computed(() => props.answers.reduce((sum, a) => sum + (a.score ?? 0), 0));
const correctCount = computed(() => props.answers.filter((a) => a.is_correct === true).length);

function formatDate(date: string | null) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function questionTypeColor(type: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (type === 'esai' || type === 'isian_singkat') return 'destructive';
    if (type === 'pilihan_ganda' || type === 'benar_salah') return 'secondary';
    return 'outline';
}
</script>

<template>
    <Head :title="`Nilai - ${examSession.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <PageHeader :title="examSession.name" :icon="Trophy" :description="`${examSession.subject} — ${formatDate(attempt.submitted_at)}`" />

            <!-- Score Summary -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatsCard
                    title="Nilai"
                    :value="attempt.score !== null ? attempt.score.toFixed(2) : '-'"
                    :icon="Award"
                    :icon-color="attempt.pass_status === 'lulus' ? 'bg-green-100 text-green-600' : attempt.pass_status === 'remedial' ? 'bg-red-100 text-red-600' : 'bg-primary/10 text-primary'"
                />
                <StatsCard
                    v-if="examSession.kkm"
                    title="KKM"
                    :value="examSession.kkm"
                    :icon="BookOpen"
                />
                <StatsCard
                    title="Status"
                    :value="attempt.pass_status === 'lulus' ? 'LULUS' : attempt.pass_status === 'remedial' ? 'REMEDIAL' : '-'"
                    :icon="attempt.pass_status === 'lulus' ? CheckCircle : XCircle"
                    :icon-color="attempt.pass_status === 'lulus' ? 'bg-green-100 text-green-600' : attempt.pass_status === 'remedial' ? 'bg-red-100 text-red-600' : 'bg-primary/10 text-primary'"
                />
            </div>

            <!-- Per-question Detail -->
            <h3 class="text-lg font-semibold">Detail Jawaban</h3>

            <div class="space-y-4">
                <Card v-for="(answer, index) in answers" :key="answer.id">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-sm">Soal {{ index + 1 }}</CardTitle>
                            <div class="flex items-center gap-2">
                                <Badge :variant="questionTypeColor(answer.question.type)">
                                    {{ answer.question.type_label }}
                                </Badge>
                                <Badge variant="outline">
                                    {{ answer.score !== null ? answer.score : '?' }}/{{ answer.question.points }}
                                </Badge>
                                <Badge v-if="answer.is_correct === true" variant="default">Benar</Badge>
                                <Badge v-else-if="answer.is_correct === false" variant="destructive">Salah</Badge>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <!-- Question -->
                        <div class="prose prose-sm dark:prose-invert max-w-none" v-html="answer.question.content"></div>

                        <img v-if="answer.question.media_url" :src="answer.question.media_url" alt="Media soal" class="max-h-48 rounded-lg" />

                        <!-- Options (for PG/BS) -->
                        <div v-if="answer.question.options.length > 0" class="space-y-1">
                            <div
                                v-for="opt in answer.question.options"
                                :key="opt.label"
                                class="flex items-start gap-2 rounded-md border p-2 text-sm"
                                :class="{
                                    'border-green-500 bg-green-50 dark:bg-green-900/20': opt.is_correct,
                                    'border-red-400 bg-red-50 dark:bg-red-900/20': !opt.is_correct && answer.answer === opt.label,
                                }"
                            >
                                <span class="font-medium min-w-5">{{ opt.label }}.</span>
                                <span v-html="opt.content"></span>
                                <Badge v-if="opt.is_correct" variant="default" class="ml-auto shrink-0 text-xs">Benar</Badge>
                                <Badge v-if="answer.answer === opt.label && !opt.is_correct" variant="destructive" class="ml-auto shrink-0 text-xs">Jawaban Anda</Badge>
                                <Badge v-if="answer.answer === opt.label && opt.is_correct" variant="default" class="ml-auto shrink-0 text-xs">Jawaban Anda</Badge>
                            </div>
                        </div>

                        <!-- Student answer for essay -->
                        <div v-if="answer.question.type === 'esai' || answer.question.type === 'isian_singkat'">
                            <p class="text-sm font-medium text-muted-foreground mb-1">Jawaban Anda:</p>
                            <div v-if="answer.answer" class="rounded-md border bg-muted/50 p-3">
                                <div v-if="answer.question.type === 'esai'" class="prose prose-sm dark:prose-invert max-w-none" v-html="answer.answer"></div>
                                <p v-else>{{ answer.answer }}</p>
                            </div>
                            <p v-else class="text-sm text-muted-foreground italic">Tidak dijawab</p>
                        </div>

                        <!-- Feedback from teacher -->
                        <div v-if="answer.feedback" class="rounded-md border border-blue-200 bg-blue-50 dark:bg-blue-900/20 p-3">
                            <p class="text-sm font-medium text-blue-800 dark:text-blue-300 mb-1">Komentar Guru:</p>
                            <p class="text-sm">{{ answer.feedback }}</p>
                        </div>

                        <!-- Explanation -->
                        <div v-if="answer.question.explanation">
                            <Separator class="my-2" />
                            <p class="text-sm font-medium text-muted-foreground mb-1">Pembahasan:</p>
                            <div class="rounded-md border bg-amber-50 dark:bg-amber-900/20 p-3 prose prose-sm dark:prose-invert max-w-none" v-html="answer.question.explanation"></div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
