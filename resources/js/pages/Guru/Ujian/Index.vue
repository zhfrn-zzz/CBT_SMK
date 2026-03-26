<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTableToolbar from '@/components/DataTableToolbar.vue';
import EmptyState from '@/components/EmptyState.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
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
import { Badge } from '@/components/ui/badge';
import { ClipboardList, Eye, MonitorCheck, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-vue-next';
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

function statusLabel(status: ExamStatus) {
    const map: Record<ExamStatus, string> = {
        draft: 'Draf',
        scheduled: 'Dijadwalkan',
        active: 'Berlangsung',
        completed: 'Selesai',
        archived: 'Diarsipkan',
    };
    return map[status] ?? status;
}
</script>

<template>
    <Head title="Sesi Ujian" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <PageHeader title="Ujian" description="Kelola sesi ujian" :icon="ClipboardList">
                <template #actions>
                    <Button v-if="examSessions.data.length > 0" size="sm" as-child>
                        <Link href="/guru/ujian/create">
                            <Plus class="size-4" />
                            Buat Ujian
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <DataTableToolbar v-model="search" search-placeholder="Cari ujian...">
                <template #filters>
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
                </template>
            </DataTableToolbar>

            <div v-if="examSessions.data.length > 0" class="overflow-x-auto rounded-lg border bg-card">
                <Table class="min-w-[800px]">
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Nama Ujian</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Mata Pelajaran</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Waktu</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Durasi</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Peserta</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Status</TableHead>
                            <TableHead class="w-[60px] text-xs font-semibold uppercase tracking-wider" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="exam in examSessions.data"
                            :key="exam.id"
                            class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors"
                        >
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
                                <StatusBadge :label="statusLabel(exam.status)" :variant="statusVariant(exam.status)" />
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
                                            <Link :href="`/guru/ujian/${exam.id}`">
                                                <Eye class="mr-2 size-4" />Lihat
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem as-child>
                                            <Link :href="`/guru/ujian/${exam.id}/edit`">
                                                <Pencil class="mr-2 size-4" />Edit
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem v-if="exam.status === 'active'" as-child>
                                            <Link :href="`/guru/ujian/${exam.id}/proctor`">
                                                <MonitorCheck class="mr-2 size-4" />Proctor
                                            </Link>
                                        </DropdownMenuItem>
                                        <ConfirmDialog
                                            title="Hapus Sesi Ujian"
                                            :description="`Apakah Anda yakin ingin menghapus ${exam.name}? Semua data attempt siswa akan ikut terhapus.`"
                                            confirm-label="Hapus"
                                            variant="destructive"
                                            @confirm="deleteItem(exam.id)"
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
                v-else
                :icon="ClipboardList"
                title="Belum ada ujian"
                description="Buat sesi ujian pertama Anda untuk mulai menyelenggarakan ujian."
            >
                <template #action>
                    <Button size="sm" as-child>
                        <Link href="/guru/ujian/create">
                            <Plus class="size-4" />
                            Buat Ujian
                        </Link>
                    </Button>
                </template>
            </EmptyState>

            <Pagination
                :links="examSessions.links"
                :from="examSessions.from"
                :to="examSessions.to"
                :total="examSessions.total"
            />
        </div>
    </AppLayout>
</template>
