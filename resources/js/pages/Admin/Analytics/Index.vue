<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
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
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Analitik Nilai</h1>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-4">
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
                        :model-value="filters.department_id?.toString() ?? ''"
                        @update:model-value="val => applyFilter('department_id', val || null)"
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Semua Jurusan" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">Semua Jurusan</SelectItem>
                            <SelectItem v-for="dept in departments" :key="dept.id" :value="dept.id.toString()">
                                {{ dept.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <!-- Classroom Stats Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Rekap Per Kelas</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="classroomStats.length === 0" class="py-8 text-center text-muted-foreground">
                        Belum ada data untuk filter yang dipilih.
                    </div>
                    <Table v-else>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Kelas</TableHead>
                                <TableHead class="text-center">Siswa</TableHead>
                                <TableHead class="text-center">Ujian</TableHead>
                                <TableHead class="text-center">Rata-rata</TableHead>
                                <TableHead class="text-center">Tertinggi</TableHead>
                                <TableHead class="text-center">Terendah</TableHead>
                                <TableHead></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="stat in classroomStats" :key="stat.classroom_id">
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
