<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatsCard from '@/Components/StatsCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import LoadingButton from '@/Components/LoadingButton.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertTriangle, BarChart3, Hash, PieChart, RefreshCw, ThumbsDown, ThumbsUp, TrendingDown } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import type { ExamAnalysis } from '@/types/analytics';
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    examSession: { id: number; name: string; subject: string };
    analysis: ExamAnalysis;
    attemptCount: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Penilaian', href: '/guru/grading' },
    { title: props.examSession.name, href: `/guru/grading/${props.examSession.id}` },
    { title: 'Analisis Soal', href: '#' },
];

let pollingInterval: ReturnType<typeof setInterval> | null = null;

const isRefreshing = ref(false);

function startPolling() {
    if (props.analysis.computing) {
        pollingInterval = setInterval(() => {
            router.reload({ only: ['analysis'] });
        }, 30000);
    }
}

function stopPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
}

onMounted(startPolling);
onUnmounted(stopPolling);

function refreshAnalysis() {
    isRefreshing.value = true;
    router.post(`/guru/grading/${props.examSession.id}/item-analysis/refresh`, {}, {
        onFinish: () => { isRefreshing.value = false; },
    });
}

function difficultyVariant(label: string) {
    if (label === 'mudah') return 'default';
    if (label === 'sedang') return 'secondary';
    return 'destructive';
}

function discriminationVariant(label: string) {
    if (label === 'baik') return 'default';
    if (label === 'cukup') return 'secondary';
    return 'destructive';
}
</script>

<template>
    <Head :title="`Analisis Soal - ${examSession.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <PageHeader title="Analisis Soal" :description="`${examSession.name} — ${examSession.subject}`" :icon="PieChart">
                <template #actions>
                    <LoadingButton :loading="isRefreshing" @click="refreshAnalysis" variant="outline" size="sm">
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Hitung Ulang
                    </LoadingButton>
                </template>
            </PageHeader>

            <Alert v-if="attemptCount < 5" variant="destructive">
                <AlertTriangle class="h-4 w-4" />
                <AlertDescription>
                    Hanya {{ attemptCount }} peserta — minimal 5 peserta untuk analisis yang bermakna.
                </AlertDescription>
            </Alert>

            <Alert v-if="analysis.computing">
                <AlertDescription>
                    Analisis sedang diproses di latar belakang. Halaman akan diperbarui setiap 30 detik.
                </AlertDescription>
            </Alert>

            <!-- Summary Cards -->
            <div v-if="!analysis.computing" class="grid gap-4 md:grid-cols-3 lg:grid-cols-6">
                <StatsCard title="Total Soal" :value="analysis.summary.total_questions" :icon="Hash" />
                <StatsCard title="Mudah" :value="analysis.summary.easy_count" :icon="ThumbsUp" icon-color="bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400" />
                <StatsCard title="Sedang" :value="analysis.summary.medium_count" :icon="BarChart3" icon-color="bg-yellow-100 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-400" />
                <StatsCard title="Sulit" :value="analysis.summary.hard_count" :icon="TrendingDown" icon-color="bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400" />
                <StatsCard title="Daya Beda Baik" :value="analysis.summary.good_discrimination_count" :icon="ThumbsUp" icon-color="bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400" />
                <StatsCard title="Daya Beda Buruk" :value="analysis.summary.poor_discrimination_count" :icon="ThumbsDown" icon-color="bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400" />
            </div>

            <!-- Item Table -->
            <div v-if="!analysis.computing && analysis.items.length > 0" class="overflow-hidden rounded-lg border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="w-12 text-xs font-semibold uppercase tracking-wider">No</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Soal</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Tipe</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">P (Tingkat Kesulitan)</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">D (Daya Beda)</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">KD</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(item, idx) in analysis.items" :key="item.question_id" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
                            <TableCell>{{ idx + 1 }}</TableCell>
                            <TableCell class="max-w-xs">
                                <span class="line-clamp-2 text-sm">{{ item.content_preview }}</span>
                                <span v-if="item.skipped" class="text-xs text-muted-foreground">(belum dinilai)</span>
                            </TableCell>
                            <TableCell class="text-xs">{{ item.type }}</TableCell>
                            <TableCell class="text-center" v-if="!item.skipped">
                                <div class="flex flex-col items-center gap-1">
                                    <span class="font-mono text-sm">{{ item.difficulty_index.toFixed(2) }}</span>
                                    <Badge :variant="difficultyVariant(item.difficulty_label)" class="text-xs">
                                        {{ item.difficulty_label }}
                                    </Badge>
                                </div>
                            </TableCell>
                            <TableCell class="text-center" v-if="!item.skipped">
                                <div class="flex flex-col items-center gap-1">
                                    <span class="font-mono text-sm">{{ item.discrimination_index.toFixed(2) }}</span>
                                    <Badge :variant="discriminationVariant(item.discrimination_label)" class="text-xs">
                                        {{ item.discrimination_label }}
                                    </Badge>
                                </div>
                            </TableCell>
                            <TableCell v-if="item.skipped" colspan="2" class="text-center text-xs text-muted-foreground">—</TableCell>
                            <TableCell>
                                <div class="flex flex-wrap gap-1">
                                    <Badge v-for="kd in item.competency_standards" :key="kd.code" variant="outline" class="text-xs">
                                        {{ kd.code }}
                                    </Badge>
                                    <span v-if="item.competency_standards.length === 0" class="text-xs text-muted-foreground">—</span>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- KD Breakdown -->
            <div v-if="!analysis.computing && analysis.kd_breakdown.length > 0" class="overflow-hidden rounded-lg border bg-card">
                <div class="border-b px-6 py-4">
                    <h3 class="font-semibold">Rekap per Kompetensi Dasar</h3>
                </div>
                <Table>
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Kode</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Nama KD</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Jumlah Soal</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Rata-rata Tingkat Kesulitan</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="kd in analysis.kd_breakdown" :key="kd.code" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
                            <TableCell class="font-mono text-sm">{{ kd.code }}</TableCell>
                            <TableCell>{{ kd.name }}</TableCell>
                            <TableCell class="text-center">{{ kd.question_count }}</TableCell>
                            <TableCell class="text-center">{{ kd.avg_score }}%</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <EmptyState
                v-if="!analysis.computing && analysis.items.length === 0"
                :icon="PieChart"
                title="Belum ada data analisis"
                description="Klik 'Hitung Ulang' untuk memulai analisis butir soal."
            />
        </div>
    </AppLayout>
</template>
