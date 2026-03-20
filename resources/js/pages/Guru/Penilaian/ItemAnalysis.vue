<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { RefreshCw, AlertTriangle } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import type { ExamAnalysis } from '@/types/analytics';
import { onMounted, onUnmounted } from 'vue';

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
    router.post(`/guru/grading/${props.examSession.id}/item-analysis/refresh`);
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
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Analisis Butir Soal</h1>
                    <p class="text-muted-foreground">{{ examSession.name }} — {{ examSession.subject }}</p>
                </div>
                <Button @click="refreshAnalysis" variant="outline" size="sm">
                    <RefreshCw class="mr-2 h-4 w-4" />
                    Hitung Ulang
                </Button>
            </div>

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
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Total Soal</CardTitle></CardHeader>
                    <CardContent><p class="text-2xl font-bold">{{ analysis.summary.total_questions }}</p></CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Mudah</CardTitle></CardHeader>
                    <CardContent><p class="text-2xl font-bold text-green-600">{{ analysis.summary.easy_count }}</p></CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Sedang</CardTitle></CardHeader>
                    <CardContent><p class="text-2xl font-bold text-yellow-600">{{ analysis.summary.medium_count }}</p></CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Sulit</CardTitle></CardHeader>
                    <CardContent><p class="text-2xl font-bold text-red-600">{{ analysis.summary.hard_count }}</p></CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Daya Beda Baik</CardTitle></CardHeader>
                    <CardContent><p class="text-2xl font-bold text-blue-600">{{ analysis.summary.good_discrimination_count }}</p></CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Daya Beda Buruk</CardTitle></CardHeader>
                    <CardContent><p class="text-2xl font-bold text-orange-600">{{ analysis.summary.poor_discrimination_count }}</p></CardContent>
                </Card>
            </div>

            <!-- Item Table -->
            <Card v-if="!analysis.computing && analysis.items.length > 0">
                <CardHeader><CardTitle>Tabel Analisis Butir</CardTitle></CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-12">No</TableHead>
                                <TableHead>Soal</TableHead>
                                <TableHead>Tipe</TableHead>
                                <TableHead class="text-center">P (Tingkat Kesulitan)</TableHead>
                                <TableHead class="text-center">D (Daya Beda)</TableHead>
                                <TableHead>KD</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(item, idx) in analysis.items" :key="item.question_id">
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
                </CardContent>
            </Card>

            <!-- KD Breakdown -->
            <Card v-if="!analysis.computing && analysis.kd_breakdown.length > 0">
                <CardHeader><CardTitle>Rekap per Kompetensi Dasar</CardTitle></CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Kode</TableHead>
                                <TableHead>Nama KD</TableHead>
                                <TableHead class="text-center">Jumlah Soal</TableHead>
                                <TableHead class="text-center">Rata-rata Tingkat Kesulitan</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="kd in analysis.kd_breakdown" :key="kd.code">
                                <TableCell class="font-mono text-sm">{{ kd.code }}</TableCell>
                                <TableCell>{{ kd.name }}</TableCell>
                                <TableCell class="text-center">{{ kd.question_count }}</TableCell>
                                <TableCell class="text-center">{{ kd.avg_score }}%</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <div v-if="!analysis.computing && analysis.items.length === 0" class="py-12 text-center text-muted-foreground">
                Belum ada data analisis. Klik "Hitung Ulang" untuk memulai.
            </div>
        </div>
    </AppLayout>
</template>
