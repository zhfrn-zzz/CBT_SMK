<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatsCard from '@/Components/StatsCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Award, BarChart3, CheckCircle, ClipboardCheck, Download, Eye, TrendingUp, Users, XCircle } from 'lucide-vue-next';
import type { BreadcrumbItem, ExamSession, ExamStatistics, GradingAttempt, GradingProgress } from '@/types';

const props = defineProps<{
    examSession: ExamSession;
    attempts: GradingAttempt[];
    statistics: ExamStatistics;
    progress: GradingProgress;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Penilaian', href: '/guru/grading' },
    { title: props.examSession.name, href: `/guru/grading/${props.examSession.id}` },
];

function formatDate(date: string) {
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function togglePublish() {
    const action = props.examSession.is_results_published ? 'unpublish' : 'publish';
    router.patch(`/guru/grading/${props.examSession.id}/${action}`, {}, { preserveScroll: true });
}

const progressPercent = props.progress.total_attempts > 0
    ? Math.round((props.progress.fully_graded / props.progress.total_attempts) * 100)
    : 0;
</script>

<template>
    <Head :title="`Penilaian - ${examSession.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />

            <PageHeader :title="examSession.name" :description="examSession.subject?.name" :icon="Award">
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/guru/grading/${examSession.id}/item-analysis`">
                            <BarChart3 class="mr-1 size-4" />
                            Analisis Soal
                        </Link>
                    </Button>

                    <Button variant="outline" size="sm" as-child>
                        <a :href="`/guru/grading/${examSession.id}/export`">
                            <Download class="mr-1 size-4" />
                            Export CSV
                        </a>
                    </Button>

                    <ConfirmDialog
                        :title="examSession.is_results_published ? 'Batalkan Publikasi?' : 'Publikasi Hasil Ujian?'"
                        :description="examSession.is_results_published
                            ? 'Siswa tidak akan bisa melihat hasil ujian setelah dibatalkan.'
                            : 'Siswa akan dapat melihat nilai dan pembahasan setelah dipublikasikan.'"
                        :confirm-label="examSession.is_results_published ? 'Ya, Batalkan' : 'Ya, Publikasikan'"
                        :variant="examSession.is_results_published ? 'destructive' : 'default'"
                        @confirm="togglePublish"
                    >
                        <Button :variant="examSession.is_results_published ? 'secondary' : 'default'" size="sm">
                            {{ examSession.is_results_published ? 'Batalkan Publikasi' : 'Publikasi Hasil' }}
                        </Button>
                    </ConfirmDialog>
                </template>
            </PageHeader>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatsCard title="Total Peserta" :value="statistics.total_students" :icon="Users" />
                <StatsCard title="Rata-rata" :value="statistics.average" :icon="BarChart3" :trend="`Tertinggi: ${statistics.highest} | Terendah: ${statistics.lowest}`" />
                <StatsCard v-if="examSession.kkm" :title="`Lulus (KKM ${examSession.kkm})`" :value="statistics.passed" :icon="CheckCircle" icon-color="bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400" />
                <StatsCard v-if="examSession.kkm" title="Remedial" :value="statistics.failed" :icon="XCircle" icon-color="bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400" />
            </div>

            <!-- Grading Progress -->
            <Card v-if="progress.ungraded_essays > 0">
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-sm font-medium">
                        <ClipboardCheck class="size-4" />
                        Progress Penilaian
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-4">
                        <Progress :model-value="progressPercent" class="flex-1" />
                        <span class="text-sm font-medium">{{ progress.fully_graded }}/{{ progress.total_attempts }} selesai</span>
                    </div>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ progress.ungraded_essays }} jawaban esai belum dinilai
                    </p>
                </CardContent>
            </Card>

            <!-- Student Results Table -->
            <div class="overflow-hidden rounded-xl border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">No</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Nama Siswa</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Username</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Nilai</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Status</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Penilaian</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(attempt, index) in attempts" :key="attempt.id" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
                            <TableCell>{{ index + 1 }}</TableCell>
                            <TableCell class="font-medium">{{ attempt.user.name }}</TableCell>
                            <TableCell>{{ attempt.user.username }}</TableCell>
                            <TableCell class="text-center">
                                <span v-if="attempt.score !== null" class="font-semibold" :class="{
                                    'text-green-600': attempt.pass_status === 'lulus',
                                    'text-red-600': attempt.pass_status === 'remedial',
                                }">
                                    {{ attempt.score.toFixed(2) }}
                                </span>
                                <span v-else class="text-muted-foreground">-</span>
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge v-if="attempt.pass_status" :variant="attempt.pass_status === 'lulus' ? 'default' : 'destructive'">
                                    {{ attempt.pass_status === 'lulus' ? 'Lulus' : 'Remedial' }}
                                </Badge>
                                <span v-else class="text-muted-foreground">-</span>
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge :variant="attempt.is_fully_graded ? 'default' : 'secondary'">
                                    {{ attempt.is_fully_graded ? 'Lengkap' : 'Belum Lengkap' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-center">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="`/guru/grading/${examSession.id}/attempt/${attempt.id}`">
                                        <Eye class="mr-1 size-4" />
                                        Detail
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <EmptyState
                v-if="attempts.length === 0"
                :icon="Award"
                title="Belum ada peserta"
                description="Belum ada peserta yang mengumpulkan."
            />
        </div>
    </AppLayout>
</template>
