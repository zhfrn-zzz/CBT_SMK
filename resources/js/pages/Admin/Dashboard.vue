<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatsCard from '@/components/StatsCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import {
    Card,
    CardAction,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Separator } from '@/components/ui/separator';
import {
    Activity,
    BarChart3,
    ChevronRight,
    GraduationCap,
    LayoutDashboard,
    Megaphone,
    Monitor,
    Pin,
    School,
    ScrollText,
    UserPlus,
    Users,
} from 'lucide-vue-next';
import type { AdminDashboardStats, AdminTodaySection, BreadcrumbItem, RecentExam } from '@/types';

defineProps<{
    stats: AdminDashboardStats;
    recentExams: RecentExam[];
    todaySection: AdminTodaySection;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard Admin', href: '/admin/dashboard' },
];

const todayDateString = new Date().toLocaleDateString('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

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

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}
</script>

<template>
    <Head title="Dashboard Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <PageHeader title="Dashboard Admin" :description="todayDateString" :icon="LayoutDashboard" />

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatsCard title="Total Siswa" :value="stats.total_siswa" :icon="Users" icon-color="bg-blue-50 text-blue-600" />
                <StatsCard title="Total Guru" :value="stats.total_guru" :icon="GraduationCap" icon-color="bg-emerald-50 text-emerald-600" />
                <StatsCard
                    title="Ujian Aktif"
                    :value="stats.active_exams"
                    :icon="Monitor"
                    icon-color="bg-amber-50 text-amber-600"
                    :trend="stats.active_exams > 0 ? 'sedang berlangsung' : undefined"
                />
                <StatsCard title="Ujian Hari Ini" :value="stats.exams_today" :icon="Activity" icon-color="bg-purple-50 text-purple-600" />
            </div>

            <!-- Active Exam Alert -->
            <Alert v-if="todaySection.activeExamAlert.count > 0" class="border-blue-200 bg-blue-50 text-blue-800">
                <Monitor class="h-4 w-4" />
                <AlertTitle>{{ todaySection.activeExamAlert.count }} ujian sedang berlangsung saat ini</AlertTitle>
                <AlertDescription class="flex items-center justify-between">
                    <span>{{ todaySection.activeExamAlert.names.join(', ') }}</span>
                    <Button variant="link" size="sm" class="text-blue-700" as-child>
                        <Link href="/admin/ujian">
                            Lihat Detail
                            <ChevronRight class="ml-1 h-4 w-4" />
                        </Link>
                    </Button>
                </AlertDescription>
            </Alert>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Left Column -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Pengumuman Terbaru -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Megaphone class="h-5 w-5 text-muted-foreground" />
                                Pengumuman Terbaru
                            </CardTitle>
                            <CardAction>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link href="/admin/pengumuman">
                                        Lihat Semua
                                        <ChevronRight class="ml-1 h-4 w-4" />
                                    </Link>
                                </Button>
                            </CardAction>
                        </CardHeader>
                        <CardContent>
                            <div v-if="todaySection.announcements.length > 0" class="space-y-4">
                                <div v-for="(announcement, index) in todaySection.announcements.slice(0, 5)" :key="announcement.id">
                                    <div class="flex items-start gap-3">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <Link href="/admin/pengumuman" class="text-sm font-medium hover:underline">
                                                    {{ announcement.title }}
                                                </Link>
                                                <Badge v-if="announcement.is_pinned" variant="outline" class="shrink-0 gap-1 text-xs">
                                                    <Pin class="h-3 w-3" />
                                                    Pin
                                                </Badge>
                                            </div>
                                            <p class="mt-0.5 text-xs text-muted-foreground">
                                                {{ announcement.user?.name ?? '-' }} · {{ formatRelativeTime(announcement.published_at) }}
                                            </p>
                                        </div>
                                    </div>
                                    <Separator v-if="index < todaySection.announcements.slice(0, 5).length - 1" class="mt-4" />
                                </div>
                            </div>
                            <EmptyState
                                v-else
                                :icon="Megaphone"
                                title="Belum ada pengumuman"
                                description="Pengumuman terbaru akan muncul di sini."
                            />
                        </CardContent>
                    </Card>

                    <!-- Audit Log Terbaru -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <ScrollText class="h-5 w-5 text-muted-foreground" />
                                Aktivitas Terbaru
                            </CardTitle>
                            <CardAction>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link href="/admin/audit-log">
                                        Lihat Semua
                                        <ChevronRight class="ml-1 h-4 w-4" />
                                    </Link>
                                </Button>
                            </CardAction>
                        </CardHeader>
                        <CardContent>
                            <div v-if="todaySection.recentAuditLogs.length > 0" class="space-y-4">
                                <div v-for="(log, index) in todaySection.recentAuditLogs.slice(0, 10)" :key="log.id">
                                    <div class="flex items-start gap-3">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium">
                                            {{ getInitials(log.user?.name ?? 'S') }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm">
                                                <span class="font-medium">{{ log.user?.name ?? 'Sistem' }}</span>
                                                <span class="text-muted-foreground"> {{ log.description ?? log.action }}</span>
                                            </p>
                                            <p class="text-xs text-muted-foreground">{{ formatRelativeTime(log.created_at) }}</p>
                                        </div>
                                    </div>
                                    <Separator v-if="index < todaySection.recentAuditLogs.slice(0, 10).length - 1" class="mt-4" />
                                </div>
                            </div>
                            <EmptyState
                                v-else
                                :icon="ScrollText"
                                title="Belum ada aktivitas"
                                description="Log aktivitas terbaru akan muncul di sini."
                            />
                        </CardContent>
                    </Card>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Quick Actions -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Aksi Cepat</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <Button variant="outline" class="h-11 w-full justify-start gap-2" as-child>
                                <Link href="/admin/users/create">
                                    <UserPlus class="h-4 w-4" />
                                    Tambah Pengguna
                                </Link>
                            </Button>
                            <Button variant="outline" class="h-11 w-full justify-start gap-2" as-child>
                                <Link href="/admin/classrooms">
                                    <School class="h-4 w-4" />
                                    Kelola Kelas
                                </Link>
                            </Button>
                            <Button variant="outline" class="h-11 w-full justify-start gap-2" as-child>
                                <Link href="/admin/analytics">
                                    <BarChart3 class="h-4 w-4" />
                                    Lihat Analitik
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Info Sistem -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Info Sistem</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">Tahun Akademik</span>
                                <span class="text-sm font-medium">2024/2025</span>
                            </div>
                            <Separator />
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">Semester</span>
                                <span class="text-sm font-medium">Genap</span>
                            </div>
                            <Separator />
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">Total Pengguna</span>
                                <span class="text-sm font-medium">{{ stats.total_users }}</span>
                            </div>
                            <Separator />
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">Status Server</span>
                                <Badge variant="default" class="bg-emerald-100 text-emerald-700">Aktif</Badge>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
