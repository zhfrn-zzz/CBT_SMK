<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import Pagination from '@/components/Pagination.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import LoadingButton from '@/Components/LoadingButton.vue';
import DataTableToolbar from '@/Components/DataTableToolbar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { Lock, MessageCircle, MessagesSquare, Pin, Plus } from 'lucide-vue-next';
import type { BreadcrumbItem, DiscussionThread, PaginatedData } from '@/types';

interface Classroom {
    id: number;
    name: string;
    subjects: { id: number; name: string }[];
}

const props = defineProps<{
    threads: PaginatedData<DiscussionThread> | null;
    classrooms: Classroom[];
    filters: { subject_id: number | null; classroom_id: number | null; search: string | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/siswa/dashboard' },
    { title: 'Forum Diskusi', href: '/siswa/forum' },
];

const classroomId = ref(String(props.filters.classroom_id ?? ''));
const subjectId = ref(String(props.filters.subject_id ?? ''));
const search = ref(props.filters.search ?? '');
const dialogOpen = ref(false);

const currentClassroom = computed(() => props.classrooms.find((c) => String(c.id) === classroomId.value));

let timer: ReturnType<typeof setTimeout>;
watch([classroomId, subjectId], () => applyFilters());
watch(search, () => { clearTimeout(timer); timer = setTimeout(applyFilters, 300); });

function applyFilters() {
    router.get('/siswa/forum', {
        classroom_id: classroomId.value || undefined,
        subject_id: subjectId.value || undefined,
        search: search.value || undefined,
    }, { preserveState: true, replace: true });
}

const form = useForm({
    subject_id: subjectId.value,
    classroom_id: classroomId.value,
    title: '',
    content: '',
});

watch([subjectId, classroomId], () => {
    form.subject_id = subjectId.value;
    form.classroom_id = classroomId.value;
});

function submitThread() {
    form.post('/siswa/forum', {
        onSuccess: () => { dialogOpen.value = false; form.reset(); },
    });
}

function timeAgo(date: string | null) {
    if (!date) return '';
    const diff = Date.now() - new Date(date).getTime();
    const m = Math.floor(diff / 60000);
    if (m < 1) return 'baru saja';
    if (m < 60) return `${m} mnt lalu`;
    const h = Math.floor(m / 60);
    if (h < 24) return `${h} jam lalu`;
    return `${Math.floor(h / 24)} hari lalu`;
}
</script>

<template>
    <Head title="Forum Diskusi" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />
            <PageHeader title="Forum Diskusi" description="Diskusi kelas" :icon="MessagesSquare">
                <template #actions>
                    <Dialog v-model:open="dialogOpen">
                        <DialogTrigger as-child>
                            <Button size="sm" :disabled="!subjectId || !classroomId">
                                <Plus class="size-4" />Buat Thread
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader><DialogTitle>Buat Thread Diskusi</DialogTitle></DialogHeader>
                            <form @submit.prevent="submitThread" class="space-y-3">
                                <div class="space-y-1">
                                    <Label>Judul</Label>
                                    <Input v-model="form.title" />
                                    <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>
                                </div>
                                <div class="space-y-1">
                                    <Label>Isi</Label>
                                    <Textarea v-model="form.content" rows="4" />
                                </div>
                                <div class="flex gap-2">
                                    <LoadingButton type="submit" :loading="form.processing">Posting</LoadingButton>
                                    <Button type="button" variant="ghost" @click="dialogOpen = false">Batal</Button>
                                </div>
                            </form>
                        </DialogContent>
                    </Dialog>
                </template>
            </PageHeader>

            <DataTableToolbar v-model="search" search-placeholder="Cari thread...">
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

            <EmptyState v-if="!classroomId || !subjectId" :icon="MessagesSquare" title="Pilih kelas dan mata pelajaran" description="Pilih kelas dan mata pelajaran untuk melihat forum." />

            <div v-else-if="threads && threads.data.length > 0" class="space-y-3">
                <Link v-for="t in threads.data" :key="t.id" :href="`/siswa/forum/${t.id}`"
                    class="block rounded-lg border bg-card p-4 transition-colors hover:bg-muted"
                    :class="{ 'border-blue-100 bg-blue-50/50 dark:border-blue-900 dark:bg-blue-950/30': t.is_pinned }">
                    <div class="flex items-start gap-3">
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium">
                            {{ t.user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="mb-1 flex flex-wrap items-center gap-2">
                                <Pin v-if="t.is_pinned" class="size-4 shrink-0 text-blue-500" />
                                <Lock v-if="t.is_locked" class="size-4 shrink-0 text-muted-foreground" />
                                <p class="text-base font-semibold">{{ t.title }}</p>
                            </div>
                            <p class="text-sm text-muted-foreground">{{ t.user?.name }} · {{ timeAgo(t.created_at) }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1 text-sm text-muted-foreground">
                            <MessageCircle class="size-4" />{{ t.reply_count }}
                        </div>
                    </div>
                </Link>
            </div>
            <EmptyState v-else-if="threads" :icon="MessagesSquare" title="Belum ada thread" description="Belum ada thread diskusi. Mulai diskusi baru!" />

            <Pagination v-if="threads" :links="threads.links" :from="threads.from" :to="threads.to" :total="threads.total" />
        </div>
    </AppLayout>
</template>
