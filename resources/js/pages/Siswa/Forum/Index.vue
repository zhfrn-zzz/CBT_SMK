<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import Pagination from '@/components/Pagination.vue';
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
import { Lock, MessageCircle, Pin, Plus } from 'lucide-vue-next';
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
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Forum Diskusi</h2>
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
                                <Button type="submit" :disabled="form.processing">Posting</Button>
                                <Button type="button" variant="ghost" @click="dialogOpen = false">Batal</Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <div class="flex flex-wrap gap-3">
                <Select v-model="classroomId">
                    <SelectTrigger class="w-[180px]"><SelectValue placeholder="Pilih Kelas" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="c in classrooms" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="subjectId" :disabled="!classroomId">
                    <SelectTrigger class="w-[220px]"><SelectValue placeholder="Pilih Mata Pelajaran" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="s in currentClassroom?.subjects ?? []" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <Input v-model="search" placeholder="Cari thread..." class="max-w-xs" />
            </div>

            <div v-if="!classroomId || !subjectId" class="rounded-lg border-2 border-dashed p-10 text-center text-muted-foreground">
                Pilih kelas dan mata pelajaran untuk melihat forum.
            </div>

            <div v-else-if="threads && threads.data.length > 0" class="divide-y rounded-md border">
                <Link v-for="t in threads.data" :key="t.id" :href="`/siswa/forum/${t.id}`"
                    class="flex items-start gap-3 p-4 hover:bg-muted/50 transition-colors">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1">
                            <Pin v-if="t.is_pinned" class="size-3 text-primary shrink-0" />
                            <Lock v-if="t.is_locked" class="size-3 text-muted-foreground shrink-0" />
                            <p class="font-medium truncate">{{ t.title }}</p>
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">{{ t.user?.name }} · {{ timeAgo(t.created_at) }}</p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0 text-muted-foreground text-sm">
                        <MessageCircle class="size-4" />{{ t.reply_count }}
                    </div>
                </Link>
            </div>
            <div v-else-if="threads" class="rounded-lg border-2 border-dashed p-10 text-center text-muted-foreground">
                Belum ada thread diskusi.
            </div>

            <Pagination v-if="threads" :links="threads.links" :from="threads.from" :to="threads.to" :total="threads.total" />
        </div>
    </AppLayout>
</template>
