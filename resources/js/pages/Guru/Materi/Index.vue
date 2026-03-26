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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { BookOpen, Eye, FileText, MoreHorizontal, Pencil, Plus, Trash2, Video } from 'lucide-vue-next';
import type { BreadcrumbItem, Material, PaginatedData } from '@/types';

interface TeachingAssignment {
    id: number;
    classroom: { id: number; name: string };
    subject: { id: number; name: string };
}

const props = defineProps<{
    assignments: TeachingAssignment[];
    materials: PaginatedData<Material> | null;
    topics: string[];
    progressStats: Material[] | null;
    filters: { subject_id: number | null; classroom_id: number | null; topic: string | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Materi', href: '/guru/materi' },
];

const subjectId = ref(String(props.filters.subject_id ?? ''));
const classroomId = ref(String(props.filters.classroom_id ?? ''));
const topic = ref(props.filters.topic ?? '');

const uniqueSubjects = computed(() => {
    const map = new Map<number, { id: number; name: string }>();
    props.assignments.forEach((a) => {
        if (!map.has(a.subject.id)) map.set(a.subject.id, a.subject);
    });
    return Array.from(map.values());
});

const filteredClassrooms = computed(() => {
    if (!subjectId.value) return [];
    return props.assignments
        .filter((a) => String(a.subject.id) === subjectId.value)
        .map((a) => a.classroom);
});

watch(subjectId, () => {
    classroomId.value = '';
    applyFilters();
});
watch(classroomId, () => applyFilters());
watch(topic, () => applyFilters());

function applyFilters() {
    router.get('/guru/materi', {
        subject_id: subjectId.value && subjectId.value !== 'all' ? subjectId.value : undefined,
        classroom_id: classroomId.value && classroomId.value !== 'all' ? classroomId.value : undefined,
        topic: topic.value && topic.value !== 'all' ? topic.value : undefined,
    }, { preserveState: true, replace: true });
}

function deleteMaterial(id: number) {
    router.delete(`/guru/materi/${id}`, { preserveScroll: true });
}

const typeIcon = (type: string) => {
    if (type === 'video_link') return Video;
    if (type === 'text') return FileText;
    return FileText;
};

const typeLabel = (type: string) => {
    if (type === 'video_link') return 'Video';
    if (type === 'text') return 'Teks';
    return 'File';
};
</script>

<template>
    <Head title="Materi Pembelajaran" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <PageHeader title="Materi" description="Kelola materi pembelajaran" :icon="BookOpen">
                <template #actions>
                    <Button size="sm" as-child>
                        <Link href="/guru/materi/create">
                            <Plus class="size-4" />
                            Tambah Materi
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <!-- Filters -->
            <DataTableToolbar>
                <template #filters>
                    <Select v-model="subjectId">
                        <SelectTrigger class="w-[220px]">
                            <SelectValue placeholder="Pilih Mata Pelajaran" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="s in uniqueSubjects" :key="s.id" :value="String(s.id)">
                                {{ s.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select v-model="classroomId" :disabled="!subjectId">
                        <SelectTrigger class="w-[180px]">
                            <SelectValue placeholder="Pilih Kelas" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="c in filteredClassrooms" :key="c.id" :value="String(c.id)">
                                {{ c.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select v-if="topics.length > 0" v-model="topic">
                        <SelectTrigger class="w-[180px]">
                            <SelectValue placeholder="Semua Topik" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua Topik</SelectItem>
                            <SelectItem v-for="t in topics" :key="t" :value="t">{{ t }}</SelectItem>
                        </SelectContent>
                    </Select>
                </template>
            </DataTableToolbar>

            <!-- Empty state: no filters selected -->
            <EmptyState
                v-if="!subjectId || !classroomId"
                :icon="BookOpen"
                title="Pilih filter"
                description="Pilih mata pelajaran dan kelas untuk melihat materi."
            />

            <!-- Materials Table -->
            <template v-else-if="materials && materials.data.length > 0">
                <div class="overflow-x-auto rounded-lg border bg-card">
                    <Table class="min-w-[600px]">
                        <TableHeader>
                            <TableRow class="bg-slate-50">
                                <TableHead class="text-xs font-semibold uppercase tracking-wider">Judul</TableHead>
                                <TableHead class="text-xs font-semibold uppercase tracking-wider">Topik</TableHead>
                                <TableHead class="text-xs font-semibold uppercase tracking-wider">Tipe</TableHead>
                                <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Status</TableHead>
                                <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="m in materials.data" :key="m.id" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
                                <TableCell>
                                    <div class="flex items-center gap-3 min-w-0">
                                        <component :is="typeIcon(m.type)" class="size-4 shrink-0 text-muted-foreground" />
                                        <div class="min-w-0">
                                            <p class="font-medium truncate">{{ m.title }}</p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ typeLabel(m.type) }}
                                                <span v-if="m.formatted_file_size"> · {{ m.formatted_file_size }}</span>
                                            </p>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell class="text-sm text-muted-foreground">{{ m.topic ?? '-' }}</TableCell>
                                <TableCell>
                                    <Badge variant="outline">{{ typeLabel(m.type) }}</Badge>
                                </TableCell>
                                <TableCell class="text-center">
                                    <Badge v-if="!m.is_published" variant="secondary">Draft</Badge>
                                    <Badge v-else variant="default">Dipublikasi</Badge>
                                </TableCell>
                                <TableCell class="text-center">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon-sm"><MoreHorizontal class="size-4" /></Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem as-child>
                                                <Link :href="`/guru/materi/${m.id}`"><Eye class="mr-2 size-4" />Lihat</Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem as-child>
                                                <Link :href="`/guru/materi/${m.id}/edit`"><Pencil class="mr-2 size-4" />Edit</Link>
                                            </DropdownMenuItem>
                                            <ConfirmDialog
                                                description="Materi dan semua data progress siswa akan dihapus. Lanjutkan?"
                                                @confirm="deleteMaterial(m.id)"
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
                <Pagination :links="materials.links" :from="materials.from" :to="materials.to" :total="materials.total" />
            </template>

            <EmptyState
                v-else-if="materials && materials.data.length === 0"
                :icon="BookOpen"
                title="Belum ada materi"
                description="Mulai tambahkan materi pembelajaran untuk kelas ini."
            >
                <template #action>
                    <Button as-child>
                        <Link href="/guru/materi/create">
                            <Plus class="size-4" />
                            Tambah Materi
                        </Link>
                    </Button>
                </template>
            </EmptyState>
        </div>
    </AppLayout>
</template>
