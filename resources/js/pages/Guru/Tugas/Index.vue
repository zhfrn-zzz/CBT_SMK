<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import DataTableToolbar from '@/Components/DataTableToolbar.vue';
import Pagination from '@/Components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Eye, FileText, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import type { Assignment, BreadcrumbItem, PaginatedData } from '@/types';

interface TeachingAssignment {
    id: number;
    classroom: { id: number; name: string };
    subject: { id: number; name: string };
}

const props = defineProps<{
    assignments: PaginatedData<Assignment>;
    teachingAssignments: TeachingAssignment[];
    filters: { subject_id: number | null; classroom_id: number | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Tugas', href: '/guru/tugas' },
];

const subjectId = ref(String(props.filters.subject_id ?? ''));
const classroomId = ref(String(props.filters.classroom_id ?? ''));

const uniqueSubjects = computed(() => {
    const map = new Map<number, { id: number; name: string }>();
    props.teachingAssignments.forEach((a) => {
        if (!map.has(a.subject.id)) map.set(a.subject.id, a.subject);
    });
    return Array.from(map.values());
});

const filteredClassrooms = computed(() =>
    props.teachingAssignments
        .filter((a) => String(a.subject.id) === subjectId.value)
        .map((a) => a.classroom),
);

watch([subjectId, classroomId], () => {
    router.get('/guru/tugas', {
        subject_id: subjectId.value && subjectId.value !== 'all' ? subjectId.value : undefined,
        classroom_id: classroomId.value && classroomId.value !== 'all' ? classroomId.value : undefined,
    }, { preserveState: true, replace: true });
});

function deadlineBadge(deadline: string): 'default' | 'secondary' | 'destructive' {
    const diff = new Date(deadline).getTime() - Date.now();
    if (diff < 0) return 'destructive';
    if (diff < 3 * 24 * 60 * 60 * 1000) return 'secondary';
    return 'default';
}

function formatDate(date: string) {
    return new Date(date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function deleteItem(id: number) {
    router.delete(`/guru/tugas/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Tugas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <PageHeader title="Tugas" description="Kelola tugas siswa" :icon="FileText">
                <template #actions>
                    <Button size="sm" as-child>
                        <Link href="/guru/tugas/create"><Plus class="size-4" />Buat Tugas</Link>
                    </Button>
                </template>
            </PageHeader>

            <DataTableToolbar>
                <template #filters>
                    <Select v-model="subjectId">
                        <SelectTrigger class="w-[220px]"><SelectValue placeholder="Semua Mata Pelajaran" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua</SelectItem>
                            <SelectItem v-for="s in uniqueSubjects" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="classroomId">
                        <SelectTrigger class="w-[180px]"><SelectValue placeholder="Semua Kelas" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua</SelectItem>
                            <SelectItem v-for="c in filteredClassrooms" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                </template>
            </DataTableToolbar>

            <div class="overflow-x-auto rounded-lg border bg-card">
                <Table class="min-w-[700px]">
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Judul</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Kelas / Mapel</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Deadline</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Submit</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Dinilai</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="a in assignments.data" :key="a.id" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
                            <TableCell class="font-medium">{{ a.title }}</TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                {{ a.classroom?.name }}<br />{{ a.subject?.name }}
                            </TableCell>
                            <TableCell>
                                <Badge :variant="deadlineBadge(a.deadline_at)">{{ formatDate(a.deadline_at) }}</Badge>
                            </TableCell>
                            <TableCell class="text-center">{{ a.submission_count ?? 0 }} / {{ a.total_students ?? '-' }}</TableCell>
                            <TableCell class="text-center">{{ a.graded_count ?? 0 }} / {{ a.submission_count ?? 0 }}</TableCell>
                            <TableCell class="text-center">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon-sm"><MoreHorizontal class="size-4" /></Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem as-child>
                                            <Link :href="`/guru/tugas/${a.id}`"><Eye class="mr-2 size-4" />Lihat</Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem as-child>
                                            <Link :href="`/guru/tugas/${a.id}/edit`"><Pencil class="mr-2 size-4" />Edit</Link>
                                        </DropdownMenuItem>
                                        <ConfirmDialog
                                            description="Semua submission akan ikut terhapus. Lanjutkan?"
                                            @confirm="deleteItem(a.id)"
                                        >
                                            <DropdownMenuItem class="text-destructive focus:text-destructive" @select.prevent>
                                                <Trash2 class="mr-2 size-4" />Hapus
                                            </DropdownMenuItem>
                                        </ConfirmDialog>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <EmptyState
                v-if="assignments.data.length === 0"
                :icon="FileText"
                title="Belum ada tugas"
                description="Mulai buat tugas untuk siswa Anda."
            >
                <template #action>
                    <Button as-child>
                        <Link href="/guru/tugas/create"><Plus class="size-4" />Buat Tugas</Link>
                    </Button>
                </template>
            </EmptyState>

            <Pagination :links="assignments.links" :from="assignments.from" :to="assignments.to" :total="assignments.total" />
        </div>
    </AppLayout>
</template>
