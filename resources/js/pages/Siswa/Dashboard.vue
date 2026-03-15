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
import { Award, CalendarDays, CheckCircle } from 'lucide-vue-next';
import type { BreadcrumbItem, RecentResult, SiswaDashboardStats } from '@/types';

defineProps<{
    stats: SiswaDashboardStats;
    recentResults: RecentResult[];
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
</script>

<template>
    <Head title="Dashboard Siswa" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h2 class="text-xl font-semibold">Dashboard Siswa</h2>

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
