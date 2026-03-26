<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import StatsCard from '@/components/StatsCard.vue';
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
import { BookOpen, Calendar, ClipboardList, Clock, Copy, Hash, IdCard, MonitorCheck, Pencil, Printer, Users } from 'lucide-vue-next';
import type { BreadcrumbItem, ExamSession, ExamStatus } from '@/types';

const props = defineProps<{
    examSession: ExamSession;
    attempts: import('@/types').ExamAttempt[];
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
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <!-- Header -->
            <PageHeader :title="examSession.name" :icon="ClipboardList">
                <template #actions>
                    <StatusBadge :label="statusLabel(examSession.status)" :variant="statusVariant(examSession.status)" />
                    <Badge variant="outline">{{ examSession.subject?.name }}</Badge>

                    <Button variant="outline" size="sm" as-child>
                        <a :href="`/guru/ujian/${examSession.id}/edit`">
                            <Pencil class="size-4" /><span class="hidden sm:inline">Edit</span>
                        </a>
                    </Button>
                    <Button v-if="examSession.status === 'active'" size="sm" as-child>
                        <a :href="`/guru/ujian/${examSession.id}/proctor`">
                            <MonitorCheck class="size-4" /><span class="hidden sm:inline">Proctor</span>
                        </a>
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <a :href="`/guru/ujian/${examSession.id}/print-pdf`" target="_blank">
                            <Printer class="size-4 sm:mr-1" /><span class="hidden sm:inline">Cetak Soal</span>
                        </a>
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        as-child
                        :disabled="!examSession.classrooms?.length"
                    >
                        <a
                            :href="`/guru/ujian/${examSession.id}/print-cards`"
                            target="_blank"
                            :class="{ 'pointer-events-none opacity-50': !examSession.classrooms?.length }"
                        >
                            <IdCard class="size-4 sm:mr-1" /><span class="hidden sm:inline">Cetak Kartu Peserta</span>
                        </a>
                    </Button>
                    <!-- Status actions -->
                    <ConfirmDialog
                        v-if="examSession.status === 'draft' || examSession.status === 'scheduled'"
                        title="Aktifkan Ujian"
                        description="Setelah diaktifkan, siswa dapat mulai mengerjakan ujian."
                        confirm-label="Aktifkan"
                        variant="default"
                        @confirm="updateStatus('active')"
                    >
                        <Button size="sm">Aktifkan Ujian</Button>
                    </ConfirmDialog>
                    <Button v-if="examSession.status === 'active'" variant="secondary" size="sm" @click="updateStatus('completed')">
                        Selesaikan Ujian
                    </Button>
                </template>
            </PageHeader>

            <!-- Info Cards -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-2 lg:grid-cols-4">
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

                <StatsCard title="Durasi" :value="examSession.duration_minutes + ' menit'" :icon="Clock" />
                <StatsCard title="Jumlah Soal" :value="examSession.pool_count ?? examSession.question_bank?.questions?.length ?? '?'" :icon="BookOpen" />
                <StatsCard title="Peserta" :value="examSession.attempts_count ?? 0" :icon="Users" />
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
            <Card v-if="attempts && attempts.length > 0">
                <CardHeader>
                    <CardTitle class="text-sm font-medium">
                        Daftar Peserta
                        <span v-if="examSession.attempts_count && examSession.attempts_count > attempts.length" class="text-xs font-normal text-muted-foreground ml-1">
                            (menampilkan {{ attempts.length }} dari {{ examSession.attempts_count }})
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto rounded-lg border bg-card">
                        <Table class="min-w-[600px]">
                            <TableHeader>
                                <TableRow class="bg-slate-50">
                                    <TableHead class="text-xs font-semibold uppercase tracking-wider">Nama</TableHead>
                                    <TableHead class="text-xs font-semibold uppercase tracking-wider">Mulai</TableHead>
                                    <TableHead class="text-xs font-semibold uppercase tracking-wider">Selesai</TableHead>
                                    <TableHead class="text-xs font-semibold uppercase tracking-wider text-center">Nilai</TableHead>
                                    <TableHead class="text-xs font-semibold uppercase tracking-wider text-center">Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="attempt in attempts" :key="attempt.id" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
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
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
