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
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    AlertTriangle,
    Award,
    BookOpen,
    Calendar,
    CheckCircle,
    ChevronRight,
    Clock,
    FileText,
    Megaphone,
    Pin,
    User,
    Video,
} from 'lucide-vue-next';
import type { BreadcrumbItem, RecentResult, SiswaDashboardStats, SiswaTodaySection } from '@/types';

const props = defineProps<{
    stats: SiswaDashboardStats;
    recentResults: RecentResult[];
    todaySection: SiswaTodaySection;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard Siswa', href: '/siswa/dashboard' },
];

const userSubtitle = computed(() => {
    const parts: string[] = [];
    if (user.value.classroom_name) parts.push(user.value.classroom_name);
    if (user.value.department_name) parts.push(user.value.department_name);
    return parts.length > 0 ? parts.join(' · ') : 'Siswa';
});

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

function formatDate(date: string | null) {
    if (!date) return '-';
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

function formatDeadline(date: string) {
    const d = new Date(date);
    const now = new Date();
    const diff = d.getTime() - now.getTime();
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(hours / 24);

    if (hours < 0) return 'Sudah lewat';
    if (hours < 24) return `${hours} jam lagi`;
    return `${days} hari lagi`;
}

function formatCountdown(date: string) {
    const d = new Date(date);
    const now = new Date();
    const diff = d.getTime() - now.getTime();
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(hours / 24);

    if (diff < 0) return 'Dimulai';
    if (hours < 1) return `${Math.floor(diff / 60000)} menit`;
    if (hours < 24) return `${hours} jam`;
    return `${days} hari`;
}

const hasUpcomingWithin24h = computed(() => {
    const now = new Date();
    const in24h = new Date(now.getTime() + 24 * 3600000);
    return props.todaySection.upcoming_exams.some((exam) => {
        const start = new Date(exam.starts_at);
        return start >= now && start <= in24h;
    });
});

const remedialCount = computed(() => {
    return props.recentResults.filter((r) => r.pass_status === 'remedial').length;
});

const materialTypeIcon = (type: string) => {
    switch (type) {
        case 'video':
            return Video;
        case 'document':
            return FileText;
        default:
            return BookOpen;
    }
};

const statusVariants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    scheduled: 'outline',
    active: 'default',
};
</script>

<template>
    <Head title="Dashboard Siswa" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <PageHeader :title="`Halo, ${user.name}! 👋`" :description="userSubtitle" />

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatsCard title="Ujian Mendatang" :value="stats.upcoming_exams" :icon="Calendar" icon-color="bg-blue-50 text-blue-600" />
                <StatsCard title="Ujian Selesai" :value="stats.completed_exams" :icon="CheckCircle" icon-color="bg-emerald-50 text-emerald-600" />
                <StatsCard
                    title="Rata-rata Nilai"
                    :value="stats.latest_score !== null ? stats.latest_score.toFixed(1) : '-'"
                    :icon="Award"
                    icon-color="bg-amber-50 text-amber-600"
                />
            </div>

            <!-- Alert Ujian Segera -->
            <Alert v-if="hasUpcomingWithin24h" class="border-amber-200 bg-amber-50 text-amber-800">
                <AlertTriangle class="h-4 w-4" />
                <AlertTitle>Ada ujian yang akan dimulai dalam 24 jam!</AlertTitle>
                <AlertDescription>
                    Pastikan kamu sudah mempersiapkan diri. Periksa jadwal ujian di bawah.
                </AlertDescription>
            </Alert>

            <!-- Alert Remedial -->
            <Alert v-if="remedialCount > 0" variant="destructive" class="border-red-200 bg-red-50 text-red-800">
                <AlertTriangle class="h-4 w-4" />
                <AlertTitle>Kamu memiliki {{ remedialCount }} ujian dengan status remedial</AlertTitle>
                <AlertDescription>
                    Hubungi guru pengampu untuk informasi jadwal remedial.
                </AlertDescription>
            </Alert>

            <!-- Main Grid -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Left Column -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Jadwal Ujian -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Calendar class="h-5 w-5 text-muted-foreground" />
                                Jadwal Ujian
                            </CardTitle>
                            <CardAction>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link href="/siswa/ujian">
                                        Lihat Semua
                                        <ChevronRight class="ml-1 h-4 w-4" />
                                    </Link>
                                </Button>
                            </CardAction>
                        </CardHeader>
                        <CardContent>
                            <div v-if="todaySection.upcoming_exams.length > 0" class="space-y-4">
                                <div v-for="(exam, index) in todaySection.upcoming_exams" :key="exam.id">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <Link href="/siswa/ujian" class="text-sm font-medium hover:underline">
                                                {{ exam.name }}
                                            </Link>
                                            <p class="text-xs text-muted-foreground">
                                                {{ exam.subject }} · {{ formatDateTime(exam.starts_at) }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <Badge :variant="statusVariants[exam.status] ?? 'secondary'" class="shrink-0">
                                                {{ exam.status_label }}
                                            </Badge>
                                            <Badge variant="outline" class="shrink-0 text-xs">
                                                <Clock class="mr-1 h-3 w-3" />
                                                {{ formatCountdown(exam.starts_at) }}
                                            </Badge>
                                        </div>
                                    </div>
                                    <Separator v-if="index < todaySection.upcoming_exams.length - 1" class="mt-4" />
                                </div>
                            </div>
                            <EmptyState
                                v-else
                                :icon="Calendar"
                                title="Belum ada ujian"
                                description="Jadwal ujian mendatang akan muncul di sini."
                            />
                        </CardContent>
                    </Card>

                    <!-- Deadline Tugas -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Clock class="h-5 w-5 text-muted-foreground" />
                                Deadline Tugas
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="todaySection.deadline_assignments.length > 0" class="space-y-4">
                                <div v-for="(assignment, index) in todaySection.deadline_assignments" :key="assignment.id">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <Link href="/siswa/tugas" class="text-sm font-medium hover:underline">
                                                {{ assignment.title }}
                                            </Link>
                                            <p class="text-xs text-muted-foreground">
                                                {{ assignment.subject }} · {{ assignment.classroom }}
                                            </p>
                                        </div>
                                        <Badge variant="destructive" class="shrink-0">
                                            {{ formatDeadline(assignment.deadline_at) }}
                                        </Badge>
                                    </div>
                                    <Separator v-if="index < todaySection.deadline_assignments.length - 1" class="mt-4" />
                                </div>
                            </div>
                            <EmptyState
                                v-else
                                :icon="Clock"
                                title="Tidak ada deadline"
                                description="Tidak ada tugas yang mendekati deadline."
                            />
                        </CardContent>
                    </Card>

                    <!-- Materi Baru -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <BookOpen class="h-5 w-5 text-muted-foreground" />
                                Materi Baru
                            </CardTitle>
                            <CardAction>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link href="/siswa/materi">
                                        Lihat Semua
                                        <ChevronRight class="ml-1 h-4 w-4" />
                                    </Link>
                                </Button>
                            </CardAction>
                        </CardHeader>
                        <CardContent>
                            <div v-if="todaySection.new_materials.length > 0" class="space-y-4">
                                <div v-for="(material, index) in todaySection.new_materials" :key="material.id">
                                    <div class="flex items-start gap-3">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-muted">
                                            <component :is="materialTypeIcon(material.type)" class="h-4 w-4 text-muted-foreground" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <Link href="/siswa/materi" class="text-sm font-medium hover:underline">
                                                {{ material.title }}
                                            </Link>
                                            <p class="text-xs text-muted-foreground">
                                                {{ material.subject }} · {{ material.classroom }} · {{ formatRelativeTime(material.created_at) }}
                                            </p>
                                        </div>
                                    </div>
                                    <Separator v-if="index < todaySection.new_materials.length - 1" class="mt-4" />
                                </div>
                            </div>
                            <EmptyState
                                v-else
                                :icon="BookOpen"
                                title="Belum ada materi baru"
                                description="Materi baru dari guru akan muncul di sini."
                            />
                        </CardContent>
                    </Card>

                    <!-- Nilai Terakhir -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Award class="h-5 w-5 text-muted-foreground" />
                                Nilai Terakhir
                            </CardTitle>
                            <CardAction>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link href="/siswa/nilai">
                                        Lihat Semua
                                        <ChevronRight class="ml-1 h-4 w-4" />
                                    </Link>
                                </Button>
                            </CardAction>
                        </CardHeader>
                        <CardContent>
                            <div v-if="recentResults.length > 0" class="space-y-4">
                                <div v-for="(result, index) in recentResults.slice(0, 5)" :key="result.id">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <Link :href="`/siswa/nilai/${result.id}`" class="text-sm font-medium hover:underline">
                                                {{ result.exam_name }}
                                            </Link>
                                            <p class="text-xs text-muted-foreground">
                                                {{ result.subject }} · {{ formatDate(result.submitted_at) }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-lg font-bold"
                                                :class="{
                                                    'text-emerald-600': result.score !== null && result.score >= 75,
                                                    'text-red-600': result.score !== null && result.score < 75,
                                                }"
                                            >
                                                {{ result.score !== null ? result.score.toFixed(1) : '-' }}
                                            </span>
                                            <Badge v-if="result.pass_status" :variant="result.pass_status === 'lulus' ? 'default' : 'destructive'">
                                                {{ result.pass_status === 'lulus' ? 'Lulus' : 'Remedial' }}
                                            </Badge>
                                        </div>
                                    </div>
                                    <Separator v-if="index < recentResults.slice(0, 5).length - 1" class="mt-4" />
                                </div>
                            </div>
                            <EmptyState
                                v-else
                                :icon="Award"
                                title="Belum ada nilai"
                                description="Hasil ujian akan muncul di sini setelah kamu menyelesaikan ujian."
                            />
                        </CardContent>
                    </Card>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Profil Mini -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Profil</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col items-center text-center">
                                <Avatar class="h-16 w-16">
                                    <AvatarFallback class="bg-primary/10 text-lg font-bold text-primary">
                                        {{ getInitials(user.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <h3 class="mt-3 text-sm font-semibold">{{ user.name }}</h3>
                                <p class="text-xs text-muted-foreground">NIS: {{ user.username }}</p>
                                <p v-if="user.classroom_name" class="text-xs text-muted-foreground">{{ user.classroom_name }}</p>
                                <Button variant="outline" size="sm" class="mt-4 w-full" as-child>
                                    <Link href="/profile">
                                        <User class="mr-2 h-4 w-4" />
                                        Lihat Profil
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Rekap Kehadiran -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Rekap Kehadiran</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <div class="mb-1 flex items-center justify-between text-xs">
                                    <span class="text-muted-foreground">Hadir</span>
                                    <span class="font-medium text-emerald-600">-</span>
                                </div>
                                <Progress :model-value="0" class="h-2" />
                            </div>
                            <div>
                                <div class="mb-1 flex items-center justify-between text-xs">
                                    <span class="text-muted-foreground">Sakit</span>
                                    <span class="font-medium text-amber-600">-</span>
                                </div>
                                <Progress :model-value="0" class="h-2" />
                            </div>
                            <div>
                                <div class="mb-1 flex items-center justify-between text-xs">
                                    <span class="text-muted-foreground">Izin</span>
                                    <span class="font-medium text-blue-600">-</span>
                                </div>
                                <Progress :model-value="0" class="h-2" />
                            </div>
                            <div>
                                <div class="mb-1 flex items-center justify-between text-xs">
                                    <span class="text-muted-foreground">Alpha</span>
                                    <span class="font-medium text-red-600">-</span>
                                </div>
                                <Progress :model-value="0" class="h-2" />
                            </div>
                            <p class="text-center text-xs text-muted-foreground">Data kehadiran akan tersedia segera.</p>
                        </CardContent>
                    </Card>

                    <!-- Badge Remedial -->
                    <Card v-if="remedialCount > 0" class="border-red-200 bg-red-50">
                        <CardHeader>
                            <CardTitle class="text-red-700">Remedial</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-center">
                                <span class="text-3xl font-bold text-red-600">{{ remedialCount }}</span>
                                <p class="mt-1 text-sm text-red-600">ujian perlu remedial</p>
                                <Button variant="outline" size="sm" class="mt-3 w-full border-red-300 text-red-700 hover:bg-red-100" as-child>
                                    <Link href="/siswa/nilai">Lihat Detail</Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Pengumuman -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Megaphone class="h-5 w-5 text-muted-foreground" />
                                Pengumuman
                            </CardTitle>
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
                                                {{ announcement.user?.name ?? '-' }} · {{ formatRelativeTime(announcement.published_at) }}
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
