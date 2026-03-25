<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTableToolbar from '@/components/DataTableToolbar.vue';
import EmptyState from '@/components/EmptyState.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
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

interface TeachingAssignment {
    id: number;
    classroom: { id: number; name: string };
    subject: { id: number; name: string };
}

const props = defineProps<{
    threads: PaginatedData<DiscussionThread> | null;
    teachingAssignments: TeachingAssignment[];
    filters: { subject_id: number | null; classroom_id: number | null; search: string | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Forum Diskusi', href: '/guru/forum' },
];

const subjectId = ref(String(props.filters.subject_id ?? ''));
const classroomId = ref(String(props.filters.classroom_id ?? ''));
const search = ref(props.filters.search ?? '');
const dialogOpen = ref(false);

const uniqueSubjects = computed(() => {
    const map = new Map<number, { id: number; name: string }>();
    props.teachingAssignments.forEach((a) => { if (!map.has(a.subject.id)) map.set(a.subject.id, a.subject); });
    return Array.from(map.values());
});

const filteredClassrooms = computed(() =>
    props.teachingAssignments.filter((a) => String(a.subject.id) === subjectId.value).map((a) => a.classroom),
);

let timer: ReturnType<typeof setTimeout>;
watch([subjectId, classroomId], () => applyFilters());
watch(search, () => { clearTimeout(timer); timer = setTimeout(applyFilters, 300); });

function applyFilters() {
    router.get('/guru/forum', {
        subject_id: subjectId.value || undefined,
        classroom_id: classroomId.value || undefined,
        search: search.value || undefined,
    }, { preserveState: true, replace: true });
}

const form = useForm({ subject_id: '', classroom_id: '', title: '', content: '' });
watch(subjectId, () => { form.subject_id = subjectId.value; form.classroom_id = classroomId.value; });
watch(classroomId, () => { form.classroom_id = classroomId.value; });

function submitThread() {
    form.post('/guru/forum', {
        onSuccess: () => { dialogOpen.value = false; form.reset(); },
    });
}

function timeAgo(date: string | null) {
    if (!date) return '';
    const diff = Date.now() - new Date(date).getTime();
    const minutes = Math.floor(diff / 60000);
    if (minutes < 1) return 'baru saja';
    if (minutes < 60) return `${minutes} menit lalu`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} jam lalu`;
    return `${Math.floor(hours / 24)} hari lalu`;
}
</script>

<template>
    <Head title="Forum Diskusi" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />

            <PageHeader title="Forum Diskusi" description="Diskusi dengan siswa" :icon="MessagesSquare">
                <template #actions>
                    <Dialog v-model:open="dialogOpen">
                        <DialogTrigger as-child>
                            <Button size="sm" :disabled="!subjectId || !classroomId">
                                <Plus class="size-4" />
                                Buat Thread
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Buat Thread Diskusi</DialogTitle>
                            </DialogHeader>
                            <form @submit.prevent="submitThread" class="space-y-3">
                                <div class="space-y-1">
                                    <Label class="font-semibold text-sm">Judul</Label>
                                    <Input v-model="form.title" class="h-11" />
                                    <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>
                                </div>
                                <div class="space-y-1">
                                    <Label class="font-semibold text-sm">Isi</Label>
                                    <Textarea v-model="form.content" rows="4" />
                                    <p v-if="form.errors.content" class="text-xs text-destructive">{{ form.errors.content }}</p>
                                </div>
                                <div class="flex items-center justify-end gap-3 pt-2">
                                    <Button type="button" variant="outline" @click="dialogOpen = false">Batal</Button>
                                    <LoadingButton :loading="form.processing" type="submit">Posting</LoadingButton>
                                </div>
                            </form>
                        </DialogContent>
                    </Dialog>
                </template>
            </PageHeader>

            <DataTableToolbar v-model="search" search-placeholder="Cari thread...">
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
                Pilih mata pelajaran dan kelas untuk melihat forum.
            </div>

            <div v-else-if="threads && threads.data.length > 0" class="space-y-3">
                <Link
                    v-for="t in threads.data" :key="t.id"
                    :href="`/guru/forum/${t.id}`"
                    class="block rounded-xl border bg-card p-4 transition-shadow hover:shadow-sm"
                    :class="{ 'border-blue-100 bg-blue-50/50 dark:border-blue-900 dark:bg-blue-950/30': t.is_pinned }"
                >
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
                            <p class="text-sm text-muted-foreground">
                                {{ t.user?.name }} · {{ timeAgo(t.created_at) }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1 text-sm text-muted-foreground">
                            <MessageCircle class="size-4" />
                            {{ t.reply_count }}
                        </div>
                    </div>
                </Link>
            </div>

            <EmptyState
                v-else-if="threads"
                :icon="MessagesSquare"
                title="Belum ada thread diskusi"
                description="Buat thread pertama untuk memulai diskusi dengan siswa."
            >
                <template #action>
                    <Button size="sm" :disabled="!subjectId || !classroomId" @click="dialogOpen = true">
                        <Plus class="size-4" />
                        Buat Thread
                    </Button>
                </template>
            </EmptyState>

            <Pagination v-if="threads" :links="threads.links" :from="threads.from" :to="threads.to" :total="threads.total" />
        </div>
    </AppLayout>
</template>
