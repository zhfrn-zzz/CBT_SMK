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
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-vue-next';
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
        subject_id: subjectId.value || undefined,
        classroom_id: classroomId.value || undefined,
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
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Tugas & Assignment</h2>
                <Button size="sm" as-child>
                    <Link href="/guru/tugas/create"><Plus class="size-4" />Buat Tugas</Link>
                </Button>
            </div>

            <div class="flex flex-wrap gap-3">
                <Select v-model="subjectId">
                    <SelectTrigger class="w-[220px]"><SelectValue placeholder="Semua Mata Pelajaran" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">Semua</SelectItem>
                        <SelectItem v-for="s in uniqueSubjects" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="classroomId">
                    <SelectTrigger class="w-[180px]"><SelectValue placeholder="Semua Kelas" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">Semua</SelectItem>
                        <SelectItem v-for="c in filteredClassrooms" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Judul</TableHead>
                            <TableHead>Kelas / Mapel</TableHead>
                            <TableHead>Deadline</TableHead>
                            <TableHead class="text-center">Submit</TableHead>
                            <TableHead class="text-center">Dinilai</TableHead>
                            <TableHead>Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="a in assignments.data" :key="a.id">
                            <TableCell class="font-medium">{{ a.title }}</TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                {{ a.classroom?.name }}<br />{{ a.subject?.name }}
                            </TableCell>
                            <TableCell>
                                <Badge :variant="deadlineBadge(a.deadline_at)">{{ formatDate(a.deadline_at) }}</Badge>
                            </TableCell>
                            <TableCell class="text-center">{{ a.submission_count ?? 0 }} / {{ a.total_students ?? '-' }}</TableCell>
                            <TableCell class="text-center">{{ a.graded_count ?? 0 }} / {{ a.submission_count ?? 0 }}</TableCell>
                            <TableCell>
                                <div class="flex gap-1">
                                    <Button variant="ghost" size="icon-sm" as-child>
                                        <Link :href="`/guru/tugas/${a.id}`"><Eye class="size-4" /></Link>
                                    </Button>
                                    <Button variant="ghost" size="icon-sm" as-child>
                                        <Link :href="`/guru/tugas/${a.id}/edit`"><Pencil class="size-4" /></Link>
                                    </Button>
                                    <AlertDialog>
                                        <AlertDialogTrigger as-child>
                                            <Button variant="ghost" size="icon-sm"><Trash2 class="size-4 text-destructive" /></Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>Hapus Tugas</AlertDialogTitle>
                                                <AlertDialogDescription>Semua submission akan ikut terhapus. Lanjutkan?</AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                                <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="deleteItem(a.id)">Hapus</AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="assignments.data.length === 0">
                            <TableCell :colspan="6" class="text-center text-muted-foreground">Belum ada tugas.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination :links="assignments.links" :from="assignments.from" :to="assignments.to" :total="assignments.total" />
        </div>
    </AppLayout>
</template>
