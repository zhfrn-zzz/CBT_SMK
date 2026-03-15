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
import { CalendarDays, ClipboardCheck, GraduationCap } from 'lucide-vue-next';
import type { BreadcrumbItem, GuruDashboardStats, RecentExam } from '@/types';

defineProps<{
    stats: GuruDashboardStats;
    recentExams: RecentExam[];
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
