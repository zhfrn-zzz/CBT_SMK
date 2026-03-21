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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { AlertTriangle, CalendarDays, ClipboardList, GraduationCap, Megaphone, ScrollText, Users } from 'lucide-vue-next';
import type { AdminDashboardStats, AdminTodaySection, BreadcrumbItem, RecentExam } from '@/types';

defineProps<{
    stats: AdminDashboardStats;
    recentExams: RecentExam[];
    todaySection: AdminTodaySection;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard Admin', href: '/admin/dashboard' },
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
    <Head title="Dashboard Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h2 class="text-xl font-semibold">Dashboard Administrator</h2>

            <!-- Active Exam Alert -->
            <Card v-if="todaySection.activeExamAlert.count > 0" class="border-orange-300 bg-orange-50 dark:border-orange-800 dark:bg-orange-950">
                <CardContent class="flex items-start gap-3 p-4">
                    <AlertTriangle class="mt-0.5 size-5 shrink-0 text-orange-600 dark:text-orange-400" />
                    <div>
                        <p class="font-medium text-orange-800 dark:text-orange-200">
                            {{ todaySection.activeExamAlert.count }} ujian sedang berlangsung
                        </p>
                        <p class="mt-1 text-sm text-orange-700 dark:text-orange-300">
                            {{ todaySection.activeExamAlert.names.join(', ') }}
                        </p>
                    </div>
                </CardContent>
            </Card>

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
                                        <Link href="/admin/pengumuman" class="text-sm font-medium hover:underline">
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

                    <!-- Aktivitas Terbaru -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <ScrollText class="size-4" />
                                Aktivitas Terbaru
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="todaySection.recentAuditLogs.length > 0" class="space-y-3">
                                <div v-for="log in todaySection.recentAuditLogs.slice(0, 5)" :key="log.id" class="flex items-start gap-2">
                                    <div class="min-w-0">
                                        <Link href="/admin/audit-log" class="text-sm hover:underline">
                                            <span class="font-medium">{{ log.user?.name ?? 'Sistem' }}</span>
                                            <span class="text-muted-foreground"> — {{ log.description ?? log.action }}</span>
                                        </Link>
                                        <p class="text-xs text-muted-foreground">{{ formatRelativeTime(log.created_at) }}</p>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">Belum ada aktivitas</p>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div class="grid auto-rows-min gap-4 md:grid-cols-3 lg:grid-cols-5">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <Users class="size-4" />
                            Total Pengguna
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ stats.total_users }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ stats.total_guru }} guru, {{ stats.total_siswa }} siswa
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <ClipboardList class="size-4" />
                            Ujian Aktif
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ stats.active_exams }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <CalendarDays class="size-4" />
                            Ujian Hari Ini
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ stats.exams_today }}</p>
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
                                <TableHead>Guru</TableHead>
                                <TableHead>Tanggal</TableHead>
                                <TableHead class="text-center">Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="exam in recentExams" :key="exam.id">
                                <TableCell class="font-medium">{{ exam.name }}</TableCell>
                                <TableCell>{{ exam.subject }}</TableCell>
                                <TableCell>{{ exam.guru }}</TableCell>
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
