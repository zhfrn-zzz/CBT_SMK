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
import { Award, BookOpen, CalendarDays, CheckCircle, Clock, Megaphone } from 'lucide-vue-next';
import type { BreadcrumbItem, RecentResult, SiswaDashboardStats, SiswaTodaySection } from '@/types';

defineProps<{
    stats: SiswaDashboardStats;
    recentResults: RecentResult[];
    todaySection: SiswaTodaySection;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard Siswa', href: '/siswa/dashboard' },
];

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

const statusVariants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    scheduled: 'outline',
    active: 'default',
};
</script>

<template>
    <Head title="Dashboard Siswa" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h2 class="text-xl font-semibold">Dashboard Siswa</h2>

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
                                        <p class="text-sm font-medium">{{ announcement.title }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ announcement.user?.name ?? '-' }} · {{ formatRelativeTime(announcement.published_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">Tidak ada pengumuman terbaru</p>
                        </CardContent>
                    </Card>

                    <!-- Ujian Hari Ini / Minggu Ini -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <CalendarDays class="size-4" />
                                Ujian Minggu Ini
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="todaySection.upcoming_exams.length > 0" class="space-y-3">
                                <div v-for="exam in todaySection.upcoming_exams" :key="exam.id" class="flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <Link href="/siswa/ujian" class="text-sm font-medium hover:underline">
                                            {{ exam.name }}
                                        </Link>
                                        <p class="text-xs text-muted-foreground">
                                            {{ exam.subject }} · {{ formatDateTime(exam.starts_at) }}
                                        </p>
                                    </div>
                                    <Badge :variant="statusVariants[exam.status] ?? 'secondary'" class="shrink-0">
                                        {{ exam.status_label }}
                                    </Badge>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">Tidak ada ujian minggu ini</p>
                        </CardContent>
                    </Card>

                    <!-- Deadline Tugas -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <Clock class="size-4" />
                                Deadline Tugas
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="todaySection.deadline_assignments.length > 0" class="space-y-3">
                                <div v-for="assignment in todaySection.deadline_assignments" :key="assignment.id" class="flex items-center justify-between gap-2">
                                    <div class="min-w-0">
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
                            </div>
                            <p v-else class="text-sm text-muted-foreground">Tidak ada tugas yang mendekati deadline</p>
                        </CardContent>
                    </Card>

                    <!-- Materi Baru -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <BookOpen class="size-4" />
                                Materi Baru
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="todaySection.new_materials.length > 0" class="space-y-3">
                                <div v-for="material in todaySection.new_materials" :key="material.id">
                                    <Link href="/siswa/materi" class="text-sm font-medium hover:underline">
                                        {{ material.title }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground">
                                        {{ material.subject }} · {{ material.classroom }} · {{ formatRelativeTime(material.created_at) }}
                                    </p>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">Tidak ada materi baru minggu ini</p>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <CalendarDays class="size-4" />
                            Ujian Mendatang
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ stats.upcoming_exams }}</p>
                        <Button v-if="stats.upcoming_exams > 0" variant="link" size="sm" class="px-0" as-child>
                            <Link href="/siswa/ujian">Lihat Ujian</Link>
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <CheckCircle class="size-4" />
                            Ujian Selesai
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ stats.completed_exams }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <Award class="size-4" />
                            Nilai Terakhir
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">
                            {{ stats.latest_score !== null ? stats.latest_score.toFixed(2) : '-' }}
                        </p>
                        <Button variant="link" size="sm" class="px-0" as-child>
                            <Link href="/siswa/nilai">Lihat Semua Nilai</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <!-- Recent Results -->
            <Card v-if="recentResults.length > 0">
                <CardHeader>
                    <CardTitle class="text-sm font-medium">Nilai Terbaru</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama Ujian</TableHead>
                                <TableHead>Mata Pelajaran</TableHead>
                                <TableHead class="text-center">Nilai</TableHead>
                                <TableHead class="text-center">Status</TableHead>
                                <TableHead>Tanggal</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="result in recentResults" :key="result.id">
                                <TableCell class="font-medium">
                                    <Link :href="`/siswa/nilai/${result.id}`" class="hover:underline">
                                        {{ result.exam_name }}
                                    </Link>
                                </TableCell>
                                <TableCell>{{ result.subject }}</TableCell>
                                <TableCell class="text-center">
                                    <span class="font-semibold" :class="{
                                        'text-green-600': result.pass_status === 'lulus',
                                        'text-red-600': result.pass_status === 'remedial',
                                    }">
                                        {{ result.score !== null ? result.score.toFixed(2) : '-' }}
                                    </span>
                                </TableCell>
                                <TableCell class="text-center">
                                    <Badge v-if="result.pass_status" :variant="result.pass_status === 'lulus' ? 'default' : 'destructive'">
                                        {{ result.pass_status === 'lulus' ? 'Lulus' : 'Remedial' }}
                                    </Badge>
                                    <span v-else class="text-muted-foreground">-</span>
                                </TableCell>
                                <TableCell>{{ formatDate(result.submitted_at) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
