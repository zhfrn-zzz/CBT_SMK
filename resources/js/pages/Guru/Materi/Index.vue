<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import Pagination from '@/components/Pagination.vue';
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
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Eye, FileText, Pencil, Plus, Trash2, Video } from 'lucide-vue-next';
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
        subject_id: subjectId.value || undefined,
        classroom_id: classroomId.value || undefined,
        topic: topic.value || undefined,
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

// Group materials by topic
const groupedMaterials = computed(() => {
    if (!props.materials?.data) return {};
    const groups: Record<string, Material[]> = {};
    props.materials.data.forEach((m) => {
        const key = m.topic ?? 'Tanpa Topik';
        if (!groups[key]) groups[key] = [];
        groups[key].push(m);
    });
    return groups;
});
</script>

<template>
    <Head title="Materi Pembelajaran" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />

            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Materi Pembelajaran</h2>
                <Button size="sm" as-child>
                    <Link href="/guru/materi/create">
                        <Plus class="size-4" />
                        Tambah Materi
                    </Link>
                </Button>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
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
                        <SelectItem value="">Semua Topik</SelectItem>
                        <SelectItem v-for="t in topics" :key="t" :value="t">{{ t }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Empty state -->
            <div v-if="!subjectId || !classroomId" class="rounded-lg border-2 border-dashed border-muted p-12 text-center text-muted-foreground">
                Pilih mata pelajaran dan kelas untuk melihat materi.
            </div>

            <!-- Materials grouped by topic -->
            <template v-else-if="materials && materials.data.length > 0">
                <div v-for="(items, topicName) in groupedMaterials" :key="topicName" class="space-y-2">
                    <h3 class="font-semibold text-sm text-muted-foreground uppercase tracking-wide">{{ topicName }}</h3>
                    <div class="rounded-md border divide-y">
                        <div v-for="m in items" :key="m.id" class="flex items-center justify-between p-3">
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
                            <div class="flex items-center gap-2 shrink-0">
                                <Badge v-if="!m.is_published" variant="secondary">Draft</Badge>
                                <Button variant="ghost" size="icon-sm" as-child>
                                    <Link :href="`/guru/materi/${m.id}`">
                                        <Eye class="size-4" />
                                    </Link>
                                </Button>
                                <Button variant="ghost" size="icon-sm" as-child>
                                    <Link :href="`/guru/materi/${m.id}/edit`">
                                        <Pencil class="size-4" />
                                    </Link>
                                </Button>
                                <AlertDialog>
                                    <AlertDialogTrigger as-child>
                                        <Button variant="ghost" size="icon-sm">
                                            <Trash2 class="size-4 text-destructive" />
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>Hapus Materi</AlertDialogTitle>
                                            <AlertDialogDescription>
                                                Materi dan semua data progress siswa akan dihapus. Lanjutkan?
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>Batal</AlertDialogCancel>
                                            <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="deleteMaterial(m.id)">
                                                Hapus
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            </div>
                        </div>
                    </div>
                </div>
                <Pagination v-if="materials" :links="materials.links" :from="materials.from" :to="materials.to" :total="materials.total" />
            </template>

            <div v-else-if="materials && materials.data.length === 0" class="rounded-lg border-2 border-dashed border-muted p-12 text-center text-muted-foreground">
                Belum ada materi. <Link href="/guru/materi/create" class="text-primary underline">Tambahkan materi pertama.</Link>
            </div>
        </div>
    </AppLayout>
</template>
