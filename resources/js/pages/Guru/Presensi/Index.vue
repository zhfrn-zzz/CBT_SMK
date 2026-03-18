<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { Eye, Plus } from 'lucide-vue-next';
import type { Attendance, BreadcrumbItem, PaginatedData } from '@/types';

interface TeachingAssignment {
    id: number;
    classroom: { id: number; name: string };
    subject: { id: number; name: string };
}

const props = defineProps<{
    sessions: PaginatedData<Attendance> | null;
    teachingAssignments: TeachingAssignment[];
    filters: { subject_id: number | null; classroom_id: number | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Presensi', href: '/guru/presensi' },
];

const subjectId = ref(String(props.filters.subject_id ?? ''));
const classroomId = ref(String(props.filters.classroom_id ?? ''));

const uniqueSubjects = computed(() => {
    const map = new Map<number, { id: number; name: string }>();
    props.teachingAssignments.forEach((a) => { if (!map.has(a.subject.id)) map.set(a.subject.id, a.subject); });
    return Array.from(map.values());
});

const filteredClassrooms = computed(() =>
    props.teachingAssignments.filter((a) => String(a.subject.id) === subjectId.value).map((a) => a.classroom),
);

watch([subjectId, classroomId], () => {
    router.get('/guru/presensi', {
        subject_id: subjectId.value || undefined,
        classroom_id: classroomId.value || undefined,
    }, { preserveState: true, replace: true });
});
</script>

<template>
    <Head title="Presensi" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Presensi</h2>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/guru/presensi-recap?subject_id=${subjectId}&classroom_id=${classroomId}`">
                            Rekap Presensi
                        </Link>
                    </Button>
                    <Button size="sm" as-child>
                        <Link :href="`/guru/presensi/create?subject_id=${subjectId}&classroom_id=${classroomId}`">
                            <Plus class="size-4" />Buka Sesi
                        </Link>
                    </Button>
                </div>
            </div>

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
            </div>

            <div v-if="!subjectId || !classroomId" class="rounded-lg border-2 border-dashed p-10 text-center text-muted-foreground">
                Pilih mata pelajaran dan kelas untuk melihat sesi presensi.
            </div>

            <div v-else-if="sessions" class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Pertemuan Ke-</TableHead>
                            <TableHead>Tanggal</TableHead>
                            <TableHead class="text-center">Hadir</TableHead>
                            <TableHead class="text-center">Status</TableHead>
                            <TableHead>Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="s in sessions.data" :key="s.id">
                            <TableCell class="font-medium">{{ s.meeting_number }}</TableCell>
                            <TableCell>{{ new Date(s.meeting_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}</TableCell>
                            <TableCell class="text-center">{{ (s as any).present_count ?? 0 }} / {{ (s as any).total_records ?? 0 }}</TableCell>
                            <TableCell class="text-center">
                                <Badge :variant="s.is_open ? 'default' : 'secondary'">
                                    {{ s.is_open ? 'Aktif' : 'Ditutup' }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <Button variant="ghost" size="icon-sm" as-child>
                                    <Link :href="`/guru/presensi/${s.id}`"><Eye class="size-4" /></Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="sessions.data.length === 0">
                            <TableCell :colspan="5" class="text-center text-muted-foreground">Belum ada sesi presensi.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination v-if="sessions" :links="sessions.links" :from="sessions.from" :to="sessions.to" :total="sessions.total" />
        </div>
    </AppLayout>
</template>
