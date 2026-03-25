<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTableToolbar from '@/Components/DataTableToolbar.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { BarChart3 } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import type { ClassroomStat } from '@/types/analytics';

const props = defineProps<{
    academicYears: Array<{ id: number; name: string }>;
    departments: Array<{ id: number; name: string }>;
    classroomStats: ClassroomStat[];
    classroomComparison: Array<{
        classroom_id: number;
        classroom_name: string;
        subject_id: number;
        subject_name: string;
        avg_score: number;
        student_count: number;
    }>;
    filters: { academic_year_id: number | null; department_id: number | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Analitik', href: '/admin/analytics' },
];

function applyFilter(key: string, value: string | null) {
    const filters = {
        academic_year_id: props.filters.academic_year_id,
        department_id: props.filters.department_id,
        [key]: value ? parseInt(value) : null,
    };
    router.get('/admin/analytics', filters as Record<string, unknown>, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Analitik" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <PageHeader title="Analitik" description="Statistik dan performa akademik" :icon="BarChart3" />

            <!-- Filters -->
            <DataTableToolbar>
                <template #filters>
                    <div class="min-w-48">
                        <Select
                            :model-value="filters.academic_year_id?.toString()"
                            @update:model-value="val => applyFilter('academic_year_id', val)"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih Tahun Ajaran" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="ay in academicYears" :key="ay.id" :value="ay.id.toString()">
                                    {{ ay.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="min-w-48">
                        <Select
                            :model-value="filters.department_id?.toString() ?? 'all'"
                            @update:model-value="val => applyFilter('department_id', val === 'all' ? null : val)"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Semua Jurusan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua Jurusan</SelectItem>
                                <SelectItem v-for="dept in departments" :key="dept.id" :value="dept.id.toString()">
                                    {{ dept.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </template>
            </DataTableToolbar>

            <!-- Classroom Stats Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Rekap Per Kelas</CardTitle>
                </CardHeader>
                <CardContent>
                    <EmptyState
                        v-if="classroomStats.length === 0"
                        :icon="BarChart3"
                        title="Belum ada data"
                        description="Belum ada data untuk filter yang dipilih."
                    />
                    <Table v-else>
                        <TableHeader>
                            <TableRow class="bg-slate-50 hover:bg-slate-50 dark:bg-slate-800/50">
                                <TableHead class="text-xs font-medium uppercase tracking-wider">Kelas</TableHead>
                                <TableHead class="text-center text-xs font-medium uppercase tracking-wider">Siswa</TableHead>
                                <TableHead class="text-center text-xs font-medium uppercase tracking-wider">Ujian</TableHead>
                                <TableHead class="text-center text-xs font-medium uppercase tracking-wider">Rata-rata</TableHead>
                                <TableHead class="text-center text-xs font-medium uppercase tracking-wider">Tertinggi</TableHead>
                                <TableHead class="text-center text-xs font-medium uppercase tracking-wider">Terendah</TableHead>
                                <TableHead class="text-xs font-medium uppercase tracking-wider"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(stat, index) in classroomStats" :key="stat.classroom_id" class="transition-colors hover:bg-muted/50" :class="index % 2 === 1 ? 'bg-muted/30' : ''">
                                <TableCell class="font-medium">{{ stat.classroom_name }}</TableCell>
                                <TableCell class="text-center">{{ stat.student_count }}</TableCell>
                                <TableCell class="text-center">{{ stat.exam_count }}</TableCell>
                                <TableCell class="text-center font-mono">{{ stat.avg_score }}</TableCell>
                                <TableCell class="text-center font-mono text-green-600">{{ stat.max_score }}</TableCell>
                                <TableCell class="text-center font-mono text-red-600">{{ stat.min_score }}</TableCell>
                                <TableCell>
                                    <a
                                        :href="`/admin/analytics/classroom/${stat.classroom_id}?academic_year_id=${filters.academic_year_id}`"
                                        class="text-sm text-primary hover:underline"
                                    >Detail</a>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
