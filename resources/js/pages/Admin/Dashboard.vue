<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
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
import { CalendarDays, ClipboardList, GraduationCap, Users } from 'lucide-vue-next';
import type { AdminDashboardStats, BreadcrumbItem, RecentExam } from '@/types';

defineProps<{
    stats: AdminDashboardStats;
    recentExams: RecentExam[];
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
