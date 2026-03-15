<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
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
import { BookOpen, Calendar, Clock, Copy, Hash, MonitorCheck, Pencil, Users } from 'lucide-vue-next';
import type { BreadcrumbItem, ExamSession, ExamStatus } from '@/types';

const props = defineProps<{
    examSession: ExamSession;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Ujian', href: '/guru/ujian' },
    { title: props.examSession.name, href: `/guru/ujian/${props.examSession.id}` },
];

function formatDate(date: string) {
    return new Date(date).toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function statusVariant(status: ExamStatus): 'default' | 'secondary' | 'destructive' | 'outline' {
    const map: Record<ExamStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        draft: 'secondary',
        scheduled: 'outline',
        active: 'default',
        completed: 'secondary',
        archived: 'destructive',
    };
    return map[status];
}

function statusLabel(status: ExamStatus): string {
    const labels: Record<ExamStatus, string> = {
        draft: 'Draf',
        scheduled: 'Dijadwalkan',
        active: 'Berlangsung',
        completed: 'Selesai',
        archived: 'Diarsipkan',
    };
    return labels[status];
}

function updateStatus(status: string) {
    router.patch(`/guru/ujian/${props.examSession.id}/status`, { status }, { preserveScroll: true });
}

function copyToken() {
    navigator.clipboard.writeText(props.examSession.token);
}
</script>

<template>
    <Head :title="examSession.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />

            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-xl font-semibold">{{ examSession.name }}</h2>
                    <div class="mt-1 flex items-center gap-2">
                        <Badge :variant="statusVariant(examSession.status)">
                            {{ statusLabel(examSession.status) }}
                        </Badge>
                        <Badge variant="outline">{{ examSession.subject?.name }}</Badge>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button v-if="examSession.status === 'active'" size="sm" as-child>
                        <a :href="`/guru/ujian/${examSession.id}/proctor`">
                            <MonitorCheck class="size-4" />
                            Proctor
                        </a>
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <a :href="`/guru/ujian/${examSession.id}/edit`">
                            <Pencil class="size-4" />
                            Edit
                        </a>
                    </Button>
                    <!-- Status actions -->
                    <AlertDialog v-if="examSession.status === 'draft' || examSession.status === 'scheduled'">
                        <AlertDialogTrigger as-child>
                            <Button size="sm">Aktifkan Ujian</Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Aktifkan Ujian</AlertDialogTitle>
                                <AlertDialogDescription>
                                    Setelah diaktifkan, siswa dapat mulai mengerjakan ujian.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                <AlertDialogAction @click="updateStatus('active')">
                                    Aktifkan
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                    <Button v-if="examSession.status === 'active'" variant="secondary" size="sm" @click="updateStatus('completed')">
                        Selesaikan Ujian
                    </Button>
                </div>
            </div>

            <!-- Info Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <Hash class="size-4" />
                            Token Akses
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-2">
                            <code class="text-2xl font-bold font-mono tracking-widest">{{ examSession.token }}</code>
                            <Button variant="ghost" size="icon-sm" @click="copyToken">
                                <Copy class="size-4" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <Clock class="size-4" />
                            Durasi
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">{{ examSession.duration_minutes }} menit</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <BookOpen class="size-4" />
                            Jumlah Soal
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">
                            {{ examSession.pool_count ?? examSession.question_bank?.questions?.length ?? '?' }}
                        </p>
                        <p v-if="examSession.pool_count" class="text-xs text-muted-foreground">
                            dari {{ examSession.question_bank?.questions?.length ?? '?' }} soal
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <Users class="size-4" />
                            Peserta
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">{{ examSession.attempts?.length ?? 0 }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Waktu -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-sm font-medium">
                        <Calendar class="size-4" />
                        Jadwal Ujian
                    </CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-sm text-muted-foreground">Mulai</p>
                        <p class="font-medium">{{ formatDate(examSession.starts_at) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Selesai</p>
                        <p class="font-medium">{{ formatDate(examSession.ends_at) }}</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Konfigurasi -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-medium">Konfigurasi</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 text-sm">
                        <div>
                            <p class="text-muted-foreground">Acak Soal</p>
                            <p class="font-medium">{{ examSession.is_randomize_questions ? 'Ya' : 'Tidak' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Acak Pilihan</p>
                            <p class="font-medium">{{ examSession.is_randomize_options ? 'Ya' : 'Tidak' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">KKM</p>
                            <p class="font-medium">{{ examSession.kkm ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Maks. Pindah Tab</p>
                            <p class="font-medium">{{ examSession.max_tab_switches ?? 'Unlimited' }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Kelas Peserta -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-medium">Kelas Peserta</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="c in examSession.classrooms" :key="c.id" variant="outline">
                            {{ c.name }}
                        </Badge>
                        <p v-if="!examSession.classrooms?.length" class="text-sm text-muted-foreground">
                            Belum ada kelas yang ditambahkan.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Daftar Peserta / Attempts -->
            <Card v-if="examSession.attempts && examSession.attempts.length > 0">
                <CardHeader>
                    <CardTitle class="text-sm font-medium">Daftar Peserta</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>Mulai</TableHead>
                                <TableHead>Selesai</TableHead>
                                <TableHead class="text-center">Nilai</TableHead>
                                <TableHead class="text-center">Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="attempt in examSession.attempts" :key="attempt.id">
                                <TableCell class="font-medium">{{ attempt.user?.name }}</TableCell>
                                <TableCell>{{ formatDate(attempt.started_at) }}</TableCell>
                                <TableCell>{{ attempt.submitted_at ? formatDate(attempt.submitted_at) : '-' }}</TableCell>
                                <TableCell class="text-center">
                                    {{ attempt.score !== null ? attempt.score : '-' }}
                                </TableCell>
                                <TableCell class="text-center">
                                    <Badge :variant="attempt.status === 'in_progress' ? 'default' : attempt.status === 'submitted' ? 'secondary' : 'outline'">
                                        {{ attempt.status === 'in_progress' ? 'Mengerjakan' :
                                           attempt.status === 'submitted' ? 'Dikumpulkan' : 'Dinilai' }}
                                    </Badge>
                                    <Badge v-if="attempt.is_force_submitted" variant="destructive" class="ml-1">
                                        Auto
                                    </Badge>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
