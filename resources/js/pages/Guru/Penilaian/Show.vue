<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Progress } from '@/components/ui/progress';
import { BarChart3, CheckCircle, ClipboardCheck, Download, Eye, TrendingUp, Users, XCircle } from 'lucide-vue-next';
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

            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-xl font-semibold">{{ examSession.name }}</h2>
                    <p class="text-sm text-muted-foreground">{{ examSession.subject?.name }}</p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <a :href="`/guru/grading/${examSession.id}/export`">
                            <Download class="mr-1 size-4" />
                            Export CSV
                        </a>
                    </Button>

                    <AlertDialog>
                        <AlertDialogTrigger as-child>
                            <Button :variant="examSession.is_results_published ? 'secondary' : 'default'" size="sm">
                                {{ examSession.is_results_published ? 'Batalkan Publikasi' : 'Publikasi Hasil' }}
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    {{ examSession.is_results_published ? 'Batalkan Publikasi?' : 'Publikasi Hasil Ujian?' }}
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    {{ examSession.is_results_published
                                        ? 'Siswa tidak akan bisa melihat hasil ujian setelah dibatalkan.'
                                        : 'Siswa akan dapat melihat nilai dan pembahasan setelah dipublikasikan.' }}
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                <AlertDialogAction @click="togglePublish">
                                    {{ examSession.is_results_published ? 'Ya, Batalkan' : 'Ya, Publikasikan' }}
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <Users class="size-4" />
                            Total Peserta
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">{{ statistics.total_students }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <BarChart3 class="size-4" />
                            Rata-rata
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">{{ statistics.average }}</p>
                        <p class="text-xs text-muted-foreground">
                            Tertinggi: {{ statistics.highest }} | Terendah: {{ statistics.lowest }}
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="examSession.kkm">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <CheckCircle class="size-4" />
                            Lulus (KKM {{ examSession.kkm }})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold text-green-600">{{ statistics.passed }}</p>
                    </CardContent>
                </Card>

                <Card v-if="examSession.kkm">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <XCircle class="size-4" />
                            Remedial
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold text-red-600">{{ statistics.failed }}</p>
                    </CardContent>
                </Card>
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
            <div class="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>No</TableHead>
                            <TableHead>Nama Siswa</TableHead>
                            <TableHead>Username</TableHead>
                            <TableHead class="text-center">Nilai</TableHead>
                            <TableHead class="text-center">Status</TableHead>
                            <TableHead class="text-center">Penilaian</TableHead>
                            <TableHead class="text-center">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(attempt, index) in attempts" :key="attempt.id">
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
                        <TableRow v-if="attempts.length === 0">
                            <TableCell colspan="7" class="text-center text-muted-foreground py-8">
                                Belum ada peserta yang mengumpulkan.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </AppLayout>
</template>
