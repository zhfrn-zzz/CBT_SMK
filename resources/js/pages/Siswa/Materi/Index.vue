<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import DataTableToolbar from '@/Components/DataTableToolbar.vue';
import { Badge } from '@/components/ui/badge';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { BookOpen, CheckCircle2, FileText, Video } from 'lucide-vue-next';
import type { BreadcrumbItem, Material, MaterialProgress } from '@/types';

interface Classroom {
    id: number;
    name: string;
    subjects: { id: number; name: string }[];
}

const props = defineProps<{
    classrooms: Classroom[];
    materials: Material[] | null;
    progress: Record<number, MaterialProgress>;
    topics: string[];
    progressSummary: { total: number; completed: number; percentage: number } | null;
    filters: { subject_id: number | null; classroom_id: number | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/siswa/dashboard' },
    { title: 'Materi', href: '/siswa/materi' },
];

const classroomId = ref(String(props.filters.classroom_id ?? ''));
const subjectId = ref(String(props.filters.subject_id ?? ''));

const currentClassroom = computed(() => props.classrooms.find((c) => String(c.id) === classroomId.value));

watch([classroomId, subjectId], () => {
    router.get('/siswa/materi', {
        classroom_id: classroomId.value || undefined,
        subject_id: subjectId.value || undefined,
    }, { preserveState: true, replace: true });
});

// Group by topic
const groupedMaterials = computed(() => {
    if (!props.materials) return {};
    const groups: Record<string, Material[]> = {};
    props.materials.forEach((m) => {
        const key = m.topic ?? 'Tanpa Topik';
        if (!groups[key]) groups[key] = [];
        groups[key].push(m);
    });
    return groups;
});

const typeIcon = (type: string) => type === 'video_link' ? Video : FileText;
const typeLabel = (type: string) => ({ file: 'File', video_link: 'Video', text: 'Teks' }[type] ?? type);
</script>

<template>
    <Head title="Materi Pembelajaran" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <PageHeader title="Materi" description="Materi pembelajaran" :icon="BookOpen" />

            <DataTableToolbar>
                <template #filters>
                    <Select v-model="classroomId">
                        <SelectTrigger class="w-full sm:w-[180px]"><SelectValue placeholder="Pilih Kelas" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="c in classrooms" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="subjectId" :disabled="!classroomId">
                        <SelectTrigger class="w-full sm:w-[220px]"><SelectValue placeholder="Pilih Mata Pelajaran" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="s in currentClassroom?.subjects ?? []" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                </template>
            </DataTableToolbar>

            <!-- Progress bar -->
            <div v-if="progressSummary && progressSummary.total > 0" class="rounded-lg border p-3">
                <div class="flex justify-between text-sm mb-1">
                    <span>Progress: {{ progressSummary.completed }} / {{ progressSummary.total }} materi</span>
                    <span class="font-medium">{{ progressSummary.percentage }}%</span>
                </div>
                <div class="h-2 rounded-full bg-muted overflow-hidden">
                    <div class="h-full bg-primary rounded-full transition-all" :style="{ width: `${progressSummary.percentage}%` }" />
                </div>
            </div>

            <EmptyState v-if="!classroomId || !subjectId" :icon="BookOpen" title="Pilih kelas dan mata pelajaran" description="Pilih kelas dan mata pelajaran untuk melihat materi." />

            <template v-else-if="materials && materials.length > 0">
                <div v-for="(items, topicName) in groupedMaterials" :key="topicName" class="space-y-2">
                    <h3 class="font-semibold text-sm text-muted-foreground uppercase tracking-wide">{{ topicName }}</h3>
                    <div class="divide-y rounded-md border">
                        <Link
                            v-for="m in items" :key="m.id"
                            :href="`/siswa/materi/${m.id}`"
                            class="flex items-center gap-3 p-3 hover:bg-slate-50/50 transition-colors"
                        >
                            <component :is="typeIcon(m.type)" class="size-4 shrink-0 text-muted-foreground" />
                            <div class="flex-1 min-w-0">
                                <p class="font-medium truncate">{{ m.title }}</p>
                                <p class="text-xs text-muted-foreground">{{ typeLabel(m.type) }}</p>
                            </div>
                            <CheckCircle2 v-if="progress[m.id]?.is_completed" class="size-4 shrink-0 text-green-600" />
                            <Badge v-else variant="outline" class="text-xs shrink-0">Belum</Badge>
                        </Link>
                    </div>
                </div>
            </template>

            <EmptyState v-else-if="materials" :icon="BookOpen" title="Belum ada materi" description="Belum ada materi untuk mata pelajaran ini." />
        </div>
    </AppLayout>
</template>
