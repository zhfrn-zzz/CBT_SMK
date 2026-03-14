<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { Badge } from '@/components/ui/badge';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem, ExamSession, ExamStatus, PaginatedData } from '@/types';

const props = defineProps<{
    examSessions: PaginatedData<ExamSession>;
    filters: { search?: string; status?: string };
    statuses: { value: string; label: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Ujian', href: '/guru/ujian' },
];

const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');

let debounceTimer: ReturnType<typeof setTimeout>;

watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => applyFilters(), 300);
});

watch(statusFilter, () => applyFilters());

function applyFilters() {
    router.get('/guru/ujian', {
        search: search.value || undefined,
        status: statusFilter.value && statusFilter.value !== 'all' ? statusFilter.value : undefined,
    }, { preserveState: true, replace: true });
}

function deleteItem(id: number) {
    router.delete(`/guru/ujian/${id}`, { preserveScroll: true });
}

function formatDate(date: string) {
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function statusVariant(status: ExamStatus) {
    const map: Record<ExamStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        draft: 'secondary',
        scheduled: 'outline',
        active: 'default',
        completed: 'secondary',
        archived: 'destructive',
    };
    return map[status] ?? 'secondary';
}
</script>

<template>
    <Head title="Sesi Ujian" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />

            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Sesi Ujian</h2>
                <Button size="sm" as-child>
                    <Link href="/guru/ujian/create">
                        <Plus class="size-4" />
                        Buat Ujian
                    </Link>
                </Button>
            </div>

            <div class="flex gap-3">
                <Input
                    v-model="search"
                    placeholder="Cari ujian..."
                    class="max-w-xs"
                />
                <Select v-model="statusFilter">
                    <SelectTrigger class="w-[200px]">
                        <SelectValue placeholder="Semua Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua Status</SelectItem>
                        <SelectItem
                            v-for="s in statuses"
                            :key="s.value"
                            :value="s.value"
                        >
                            {{ s.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nama Ujian</TableHead>
                            <TableHead>Mata Pelajaran</TableHead>
                            <TableHead>Waktu</TableHead>
                            <TableHead class="text-center">Durasi</TableHead>
                            <TableHead class="text-center">Peserta</TableHead>
                            <TableHead class="text-center">Status</TableHead>
                            <TableHead class="w-[120px]">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="exam in examSessions.data" :key="exam.id">
                            <TableCell>
                                <p class="font-medium">{{ exam.name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    Token: <code class="font-mono font-bold">{{ exam.token }}</code>
                                </p>
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline">{{ exam.subject?.name }}</Badge>
                            </TableCell>
                            <TableCell>
                                <div class="text-sm">
                                    <p>{{ formatDate(exam.starts_at) }}</p>
                                    <p class="text-muted-foreground">s/d {{ formatDate(exam.ends_at) }}</p>
                                </div>
                            </TableCell>
                            <TableCell class="text-center">
                                {{ exam.duration_minutes }} menit
                            </TableCell>
                            <TableCell class="text-center">
                                {{ exam.attempts_count ?? 0 }}
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge :variant="statusVariant(exam.status)">
                                    {{ exam.status === 'draft' ? 'Draf' :
                                       exam.status === 'scheduled' ? 'Dijadwalkan' :
                                       exam.status === 'active' ? 'Berlangsung' :
                                       exam.status === 'completed' ? 'Selesai' : 'Diarsipkan' }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <div class="flex gap-1">
                                    <Button variant="ghost" size="icon-sm" as-child>
                                        <Link :href="`/guru/ujian/${exam.id}`">
                                            <Eye class="size-4" />
                                        </Link>
                                    </Button>
                                    <Button variant="ghost" size="icon-sm" as-child>
                                        <Link :href="`/guru/ujian/${exam.id}/edit`">
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
                                                <AlertDialogTitle>Hapus Sesi Ujian</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    Apakah Anda yakin ingin menghapus <strong>{{ exam.name }}</strong>?
                                                    Semua data attempt siswa akan ikut terhapus.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                                <AlertDialogAction
                                                    class="bg-destructive text-white hover:bg-destructive/90"
                                                    @click="deleteItem(exam.id)"
                                                >
                                                    Hapus
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="examSessions.data.length === 0">
                            <TableCell :colspan="7" class="text-center text-muted-foreground">
                                Belum ada sesi ujian. Klik "Buat Ujian" untuk memulai.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination
                :links="examSessions.links"
                :from="examSessions.from"
                :to="examSessions.to"
                :total="examSessions.total"
            />
        </div>
    </AppLayout>
</template>
