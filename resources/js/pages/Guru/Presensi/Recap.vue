<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { CalendarCheck, Download } from 'lucide-vue-next';
import type { AttendanceRecap, BreadcrumbItem } from '@/types';

interface TeachingAssignment {
    id: number;
    classroom: { id: number; name: string };
    subject: { id: number; name: string };
}

const props = defineProps<{
    recap: AttendanceRecap[] | null;
    teachingAssignments: TeachingAssignment[];
    filters: {
        subject_id: number | null;
        classroom_id: number | null;
        start_date: string | null;
        end_date: string | null;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Presensi', href: '/guru/presensi' },
    { title: 'Rekap', href: '/guru/presensi-recap' },
];

const subjectId = ref(String(props.filters.subject_id ?? ''));
const classroomId = ref(String(props.filters.classroom_id ?? ''));
const startDate = ref(props.filters.start_date ?? '');
const endDate = ref(props.filters.end_date ?? '');

const uniqueSubjects = computed(() => {
    const map = new Map<number, { id: number; name: string }>();
    props.teachingAssignments.forEach((a) => { if (!map.has(a.subject.id)) map.set(a.subject.id, a.subject); });
    return Array.from(map.values());
});

const filteredClassrooms = computed(() =>
    props.teachingAssignments.filter((a) => String(a.subject.id) === subjectId.value).map((a) => a.classroom),
);

function applyFilters() {
    router.get('/guru/presensi-recap', {
        subject_id: subjectId.value || undefined,
        classroom_id: classroomId.value || undefined,
        start_date: startDate.value || undefined,
        end_date: endDate.value || undefined,
    }, { preserveState: true, replace: true });
}

watch([subjectId, classroomId], () => applyFilters());

function exportExcel() {
    window.open(`/guru/presensi-recap/export?subject_id=${subjectId.value}&classroom_id=${classroomId.value}`, '_blank');
}
</script>

<template>
    <Head title="Rekap Presensi" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <PageHeader title="Rekap Presensi" description="Ringkasan kehadiran siswa" :icon="CalendarCheck">
                <template #actions>
                    <LoadingButton variant="outline" size="sm" :disabled="!subjectId || !classroomId" @click="exportExcel">
                        <Download class="size-4" />
                        Export Excel
                    </LoadingButton>
                </template>
            </PageHeader>

            <div class="flex flex-wrap gap-3">
                <Select v-model="subjectId">
                    <SelectTrigger class="w-[220px]"><SelectValue placeholder="Pilih Mata Pelajaran" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="s in uniqueSubjects" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="classroomId" :disabled="!subjectId">
                    <SelectTrigger class="w-[180px]"><SelectValue placeholder="Pilih Kelas" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="c in filteredClassrooms" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <div class="flex items-center gap-2">
                    <Label class="shrink-0 text-sm">Dari</Label>
                    <Input v-model="startDate" type="date" class="w-36" @change="applyFilters" />
                    <Label class="shrink-0 text-sm">s/d</Label>
                    <Input v-model="endDate" type="date" class="w-36" @change="applyFilters" />
                </div>
            </div>

            <div v-if="!subjectId || !classroomId" class="rounded-lg border-2 border-dashed p-10 text-center text-muted-foreground">
                Pilih mata pelajaran dan kelas untuk melihat rekap.
            </div>

            <div v-else-if="recap && recap.length > 0" class="overflow-hidden rounded-lg border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Nama</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Username</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Hadir</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Izin</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Sakit</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Alfa</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">% Kehadiran</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="r in recap" :key="r.user.id" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
                            <TableCell class="font-medium">{{ r.user.name }}</TableCell>
                            <TableCell class="text-muted-foreground">{{ r.user.username }}</TableCell>
                            <TableCell class="text-center text-green-600 font-medium">{{ r.hadir }}</TableCell>
                            <TableCell class="text-center text-blue-600">{{ r.izin }}</TableCell>
                            <TableCell class="text-center text-yellow-600">{{ r.sakit }}</TableCell>
                            <TableCell class="text-center text-red-600">{{ r.alfa }}</TableCell>
                            <TableCell class="text-center">
                                <span :class="r.percentage < 75 ? 'text-red-600 font-medium' : 'text-green-600'">
                                    {{ r.percentage }}%
                                </span>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <EmptyState
                v-else-if="recap"
                :icon="CalendarCheck"
                title="Tidak ada data presensi"
                description="Tidak ada data presensi untuk filter yang dipilih."
            />
        </div>
    </AppLayout>
</template>
