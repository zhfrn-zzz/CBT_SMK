<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
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
    AlertTriangle,
    Calendar,
    ChevronRight,
    FileEdit,
    FileText,
    GraduationCap,
    Megaphone,
    MonitorPlay,
    PenLine,
    Pin,
    Plus,
    School,
    UserCheck,
} from 'lucide-vue-next';
import type { BreadcrumbItem, GuruDashboardStats, GuruTodaySection, RecentExam } from '@/types';

defineProps<{
    stats: GuruDashboardStats;
    recentExams: RecentExam[];
    todaySection: GuruTodaySection;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard Guru', href: '/guru/dashboard' },
];

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Selamat Pagi';
    if (hour < 15) return 'Selamat Siang';
    if (hour < 18) return 'Selamat Sore';
    return 'Selamat Malam';
});

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

function formatDateTime(date: string) {
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
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
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <PageHeader
                :title="`${greeting}, Bapak/Ibu ${user.name}`"
                :description="todayDateString"
                :icon="GraduationCap"
            />

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatsCard title="Kelas Saya" :value="stats.class_count" :icon="School" icon-color="bg-blue-50 text-blue-600" />
                <StatsCard title="Ujian Mendatang" :value="stats.upcoming_exams" :icon="Calendar" icon-color="bg-amber-50 text-amber-600" />
                <StatsCard
                    title="Esai Pending"
                    :value="stats.ungraded_essays"
                    :icon="FileEdit"
                    icon-color="bg-red-50 text-red-600"
                    :trend="stats.ungraded_essays > 0 ? 'perlu dinilai' : undefined"
                />
                <StatsCard title="Total Materi" :value="stats.pending_submissions" :icon="FileText" icon-color="bg-emerald-50 text-emerald-600" />
            </div>

            <!-- Alert Esai Pending -->
            <Alert v-if="stats.ungraded_essays > 0" variant="destructive" class="border-amber-200 bg-amber-50 text-amber-800">
                <AlertTriangle class="h-4 w-4" />
                <AlertTitle>Anda memiliki {{ stats.ungraded_essays }} jawaban esai yang belum dinilai</AlertTitle>
                <AlertDescription>
                    <Button variant="link" size="sm" class="h-auto p-0 text-amber-700 underline" as-child>
                        <Link href="/guru/penilaian">Nilai Sekarang</Link>
                    </Button>
                </AlertDescription>
            </Alert>

            <!-- Main Grid -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Left Column -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Ujian Aktif Real-time -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <MonitorPlay class="h-5 w-5 text-muted-foreground" />
                                Ujian Aktif Real-time
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="todaySection.active_exams.length > 0" class="space-y-4">
                                <div v-for="(exam, index) in todaySection.active_exams" :key="exam.id">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <Link :href="`/guru/ujian/${exam.id}`" class="text-sm font-medium hover:underline">
                                                    {{ exam.name }}
                                                </Link>
                                                <Badge variant="default" class="gap-1 bg-emerald-100 text-emerald-700">
                                                    <span class="relative flex h-2 w-2">
                                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-75" />
                                                        <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-600" />
                                                    </span>
                                                    BERLANGSUNG
                                                </Badge>
                                            </div>
                                            <p class="mt-0.5 text-xs text-muted-foreground">{{ exam.subject }}</p>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-lg font-bold">{{ exam.in_progress_count }}</span>
                                            <p class="text-xs text-muted-foreground">peserta</p>
                                        </div>
                                    </div>
                                    <Separator v-if="index < todaySection.active_exams.length - 1" class="mt-4" />
                                </div>
                            </div>
                            <EmptyState
                                v-else
                                :icon="MonitorPlay"
                                title="Tidak ada ujian aktif"
                                description="Saat ini tidak ada ujian yang sedang berlangsung."
                            />
                        </CardContent>
                    </Card>

                    <!-- Penilaian Pending -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <PenLine class="h-5 w-5 text-muted-foreground" />
                                Penilaian Pending
                            </CardTitle>
                            <CardAction>
                                <Button v-if="todaySection.pending_grading.length > 0" variant="ghost" size="sm" as-child>
                                    <Link href="/guru/penilaian">
                                        Lihat Semua
                                        <ChevronRight class="ml-1 h-4 w-4" />
                                    </Link>
                                </Button>
                            </CardAction>
                        </CardHeader>
                        <CardContent>
                            <div v-if="todaySection.pending_grading.length > 0" class="space-y-3">
                                <div
                                    v-for="item in todaySection.pending_grading"
                                    :key="item.subject"
                                    class="flex items-center justify-between rounded-lg border p-3"
                                >
                                    <div>
                                        <p class="text-sm font-medium">{{ item.subject }}</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <Badge variant="destructive">{{ item.count }} jawaban</Badge>
                                        <Button variant="outline" size="sm" as-child>
                                            <Link href="/guru/penilaian">Nilai</Link>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                            <EmptyState
                                v-else
                                :icon="PenLine"
                                title="Semua sudah dinilai"
                                description="Tidak ada jawaban esai yang menunggu penilaian."
                            />
                        </CardContent>
                    </Card>

                    <!-- Presensi Hari Ini -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <UserCheck class="h-5 w-5 text-muted-foreground" />
                                Presensi Hari Ini
                            </CardTitle>
                            <CardAction>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link href="/guru/presensi">
                                        Lihat Semua
                                        <ChevronRight class="ml-1 h-4 w-4" />
                                    </Link>
                                </Button>
                            </CardAction>
                        </CardHeader>
                        <CardContent>
                            <div v-if="todaySection.today_attendance.length > 0" class="space-y-3">
                                <div
                                    v-for="attendance in todaySection.today_attendance"
                                    :key="attendance.id"
                                    class="flex items-center justify-between rounded-lg border p-3"
                                >
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium">{{ attendance.classroom }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ attendance.subject }} · Pertemuan {{ attendance.meeting_number }}
                                        </p>
                                    </div>
                                    <Badge :variant="attendance.is_open ? 'default' : 'secondary'">
                                        {{ attendance.is_open ? 'Aktif' : 'Ditutup' }}
                                    </Badge>
                                </div>
                            </div>
                            <EmptyState
                                v-else
                                :icon="UserCheck"
                                title="Tidak ada sesi presensi"
                                description="Tidak ada sesi presensi hari ini."
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
                            <Button class="h-11 w-full justify-start gap-2" as-child>
                                <Link href="/guru/ujian/create">
                                    <Plus class="h-4 w-4" />
                                    Buat Ujian Baru
                                </Link>
                            </Button>
                            <Button variant="outline" class="h-11 w-full justify-start gap-2" as-child>
                                <Link href="/guru/materi/create">
                                    <FileText class="h-4 w-4" />
                                    Buat Materi
                                </Link>
                            </Button>
                            <Button variant="outline" class="h-11 w-full justify-start gap-2" as-child>
                                <Link href="/guru/presensi">
                                    <UserCheck class="h-4 w-4" />
                                    Buka Presensi
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Jadwal Mendatang -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Calendar class="h-5 w-5 text-muted-foreground" />
                                Jadwal Mendatang
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="recentExams.length > 0" class="space-y-4">
                                <div v-for="(exam, index) in recentExams.slice(0, 5)" :key="exam.id">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <Link :href="`/guru/ujian/${exam.id}`" class="text-sm font-medium hover:underline">
                                                {{ exam.name }}
                                            </Link>
                                            <p class="text-xs text-muted-foreground">{{ exam.subject }}</p>
                                            <p class="mt-0.5 text-xs text-muted-foreground">{{ formatDateTime(exam.starts_at) }}</p>
                                        </div>
                                        <Badge :variant="statusVariants[exam.status] ?? 'secondary'" class="shrink-0">
                                            {{ exam.status_label }}
                                        </Badge>
                                    </div>
                                    <Separator v-if="index < recentExams.slice(0, 5).length - 1" class="mt-4" />
                                </div>
                            </div>
                            <EmptyState
                                v-else
                                :icon="Calendar"
                                title="Belum ada jadwal"
                                description="Jadwal ujian mendatang akan muncul di sini."
                            />
                        </CardContent>
                    </Card>

                    <!-- Pengumuman -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Megaphone class="h-5 w-5 text-muted-foreground" />
                                Pengumuman
                            </CardTitle>
                            <CardAction>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link href="/guru/pengumuman">
                                        Lihat Semua
                                        <ChevronRight class="ml-1 h-4 w-4" />
                                    </Link>
                                </Button>
                            </CardAction>
                        </CardHeader>
                        <CardContent>
                            <div v-if="todaySection.announcements.length > 0" class="space-y-4">
                                <div v-for="(announcement, index) in todaySection.announcements.slice(0, 3)" :key="announcement.id">
                                    <div class="flex items-start gap-2">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <p class="text-sm font-medium">{{ announcement.title }}</p>
                                                <Badge v-if="announcement.is_pinned" variant="outline" class="shrink-0 gap-1 text-xs">
                                                    <Pin class="h-3 w-3" />
                                                    Pin
                                                </Badge>
                                            </div>
                                            <p class="mt-0.5 text-xs text-muted-foreground">
                                                {{ announcement.user?.name ?? '-' }} · {{ formatDate(announcement.published_at) }}
                                            </p>
                                        </div>
                                    </div>
                                    <Separator v-if="index < todaySection.announcements.slice(0, 3).length - 1" class="mt-4" />
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
                </div>
            </div>
        </div>
    </AppLayout>
</template>
