<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { CalendarDays, ClipboardCheck, GraduationCap, Megaphone, MonitorPlay, PenLine, UserCheck } from 'lucide-vue-next';
import type { BreadcrumbItem, GuruDashboardStats, GuruTodaySection, RecentExam } from '@/types';

defineProps<{
    stats: GuruDashboardStats;
    recentExams: RecentExam[];
    todaySection: GuruTodaySection;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard Guru', href: '/guru/dashboard' },
];

function formatDate(date: string) {
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function formatRelativeTime(date: string) {
    const d = new Date(date);
    const now = new Date();
    const diff = now.getTime() - d.getTime();
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (minutes < 1) return 'Baru saja';
    if (minutes < 60) return `${minutes} menit lalu`;
    if (hours < 24) return `${hours} jam lalu`;
    if (days < 7) return `${days} hari lalu`;
    return formatDate(date);
}

const statusVariants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    draft: 'secondary',
    scheduled: 'outline',
    active: 'default',
    completed: 'secondary',
    archived: 'destructive',
};
</script>

<template>
    <Head title="Dashboard Guru" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h2 class="text-xl font-semibold">Dashboard Guru</h2>

            <!-- Hari Ini Section -->
            <div class="space-y-2">
                <h3 class="text-lg font-medium">Hari Ini</h3>
                <div class="grid gap-4 md:grid-cols-2">
                    <!-- Pengumuman Terbaru -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <Megaphone class="size-4" />
                                Pengumuman Terbaru
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="todaySection.announcements.length > 0" class="space-y-3">
                                <div v-for="announcement in todaySection.announcements" :key="announcement.id" class="flex items-start gap-2">
                                    <Badge v-if="announcement.is_pinned" variant="outline" class="mt-0.5 shrink-0 text-xs">Pin</Badge>
                                    <div class="min-w-0">
                                        <Link href="/guru/pengumuman" class="text-sm font-medium hover:underline">
                                            {{ announcement.title }}
                                        </Link>
                                        <p class="text-xs text-muted-foreground">
                                            {{ announcement.user?.name ?? '-' }} · {{ formatRelativeTime(announcement.published_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">Tidak ada pengumuman terbaru</p>
                        </CardContent>
                    </Card>

                    <!-- Ujian Aktif -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <MonitorPlay class="size-4" />
                                Ujian Aktif
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="todaySection.active_exams.length > 0" class="space-y-3">
                                <div v-for="exam in todaySection.active_exams" :key="exam.id" class="flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <Link :href="`/guru/ujian/${exam.id}`" class="text-sm font-medium hover:underline">
                                            {{ exam.name }}
                                        </Link>
                                        <p class="text-xs text-muted-foreground">{{ exam.subject }}</p>
                                    </div>
                                    <Badge variant="default" class="shrink-0">
                                        {{ exam.in_progress_count }} peserta
                                    </Badge>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">Tidak ada ujian aktif saat ini</p>
                        </CardContent>
                    </Card>

                    <!-- Pending Grading -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <PenLine class="size-4" />
                                Penilaian Menunggu
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="todaySection.pending_grading.length > 0" class="space-y-3">
                                <div v-for="item in todaySection.pending_grading" :key="item.subject" class="flex items-center justify-between gap-2">
                                    <span class="text-sm">{{ item.subject }}</span>
                                    <Badge variant="destructive">{{ item.count }} jawaban</Badge>
                                </div>
                                <Button variant="link" size="sm" class="px-0" as-child>
                                    <Link href="/guru/grading">Lihat Penilaian →</Link>
                                </Button>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">Tidak ada jawaban yang perlu dinilai</p>
                        </CardContent>
                    </Card>

                    <!-- Presensi Hari Ini -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <UserCheck class="size-4" />
                                Presensi Hari Ini
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="todaySection.today_attendance.length > 0" class="space-y-3">
                                <div v-for="attendance in todaySection.today_attendance" :key="attendance.id" class="flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <Link href="/guru/presensi" class="text-sm font-medium hover:underline">
                                            {{ attendance.classroom }}
                                        </Link>
                                        <p class="text-xs text-muted-foreground">
                                            {{ attendance.subject }} · Pertemuan {{ attendance.meeting_number }}
                                        </p>
                                    </div>
                                    <Badge :variant="attendance.is_open ? 'default' : 'secondary'">
                                        {{ attendance.is_open ? 'Aktif' : 'Ditutup' }}
                                    </Badge>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">Tidak ada sesi presensi hari ini</p>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <GraduationCap class="size-4" />
                            Kelas Diampu
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ stats.class_count }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <CalendarDays class="size-4" />
                            Ujian Mendatang
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ stats.upcoming_exams }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <ClipboardCheck class="size-4" />
                            Esai Perlu Dinilai
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold" :class="{ 'text-red-600': stats.ungraded_essays > 0 }">
                            {{ stats.ungraded_essays }}
                        </p>
                        <Button v-if="stats.ungraded_essays > 0" variant="link" size="sm" class="px-0" as-child>
                            <Link href="/guru/grading">Lihat Penilaian</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <!-- Recent Exams -->
            <Card v-if="recentExams.length > 0">
                <CardHeader>
                    <CardTitle class="text-sm font-medium">Ujian Terbaru</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>Mata Pelajaran</TableHead>
                                <TableHead class="text-center">Peserta</TableHead>
                                <TableHead>Tanggal</TableHead>
                                <TableHead class="text-center">Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="exam in recentExams" :key="exam.id">
                                <TableCell class="font-medium">
                                    <Link :href="`/guru/ujian/${exam.id}`" class="hover:underline">
                                        {{ exam.name }}
                                    </Link>
                                </TableCell>
                                <TableCell>{{ exam.subject }}</TableCell>
                                <TableCell class="text-center">{{ exam.total_attempts ?? 0 }}</TableCell>
                                <TableCell>{{ formatDate(exam.starts_at) }}</TableCell>
                                <TableCell class="text-center">
                                    <Badge :variant="statusVariants[exam.status] ?? 'secondary'">
                                        {{ exam.status_label }}
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
