<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTableToolbar from '@/components/DataTableToolbar.vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ClipboardCheck, Eye, MoreHorizontal, Plus } from 'lucide-vue-next';
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
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <PageHeader title="Presensi" description="Kelola presensi siswa" :icon="ClipboardCheck">
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/guru/presensi-recap?subject_id=${subjectId}&classroom_id=${classroomId}`">
                            Rekap Presensi
                        </Link>
                    </Button>
                    <Button size="sm" as-child>
                        <Link :href="`/guru/presensi/create?subject_id=${subjectId}&classroom_id=${classroomId}`">
                            <Plus class="size-4" />
                            Buka Sesi
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <DataTableToolbar>
                <template #filters>
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
                </template>
            </DataTableToolbar>

            <div v-if="!subjectId || !classroomId" class="rounded-lg border-2 border-dashed p-10 text-center text-muted-foreground">
                Pilih mata pelajaran dan kelas untuk melihat sesi presensi.
            </div>

            <template v-else-if="sessions">
                <div v-if="sessions.data.length > 0" class="overflow-hidden rounded-lg border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow class="bg-slate-50">
                                <TableHead class="text-xs font-semibold uppercase tracking-wider">Pertemuan Ke-</TableHead>
                                <TableHead class="text-xs font-semibold uppercase tracking-wider">Tanggal</TableHead>
                                <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Hadir</TableHead>
                                <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Status</TableHead>
                                <TableHead class="w-[60px] text-xs font-semibold uppercase tracking-wider" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="s in sessions.data" :key="s.id" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
                                <TableCell class="font-medium">{{ s.meeting_number }}</TableCell>
                                <TableCell>{{ new Date(s.meeting_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}</TableCell>
                                <TableCell class="text-center">{{ (s as any).present_count ?? 0 }} / {{ (s as any).total_records ?? 0 }}</TableCell>
                                <TableCell class="text-center">
                                    <Badge :variant="s.is_open ? 'default' : 'secondary'">
                                        {{ s.is_open ? 'Aktif' : 'Ditutup' }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon-sm">
                                                <MoreHorizontal class="size-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem as-child>
                                                <Link :href="`/guru/presensi/${s.id}`">
                                                    <Eye class="mr-2 size-4" />Lihat
                                                </Link>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <EmptyState
                    v-else
                    :icon="ClipboardCheck"
                    title="Belum ada sesi presensi"
                    description="Buka sesi presensi pertama untuk mulai mencatat kehadiran siswa."
                >
                    <template #action>
                        <Button size="sm" as-child>
                            <Link :href="`/guru/presensi/create?subject_id=${subjectId}&classroom_id=${classroomId}`">
                                <Plus class="size-4" />
                                Buka Sesi
                            </Link>
                        </Button>
                    </template>
                </EmptyState>
            </template>

            <Pagination v-if="sessions" :links="sessions.links" :from="sessions.from" :to="sessions.to" :total="sessions.total" />
        </div>
    </AppLayout>
</template>
