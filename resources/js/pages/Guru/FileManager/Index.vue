<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatsCard from '@/components/StatsCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ArrowDownAZ, ArrowUpDown, File, FolderOpen, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem, FileManagerFilters, GuruFile } from '@/types';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref } from 'vue';

const props = defineProps<{
    files: GuruFile[];
    filters: FileManagerFilters;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard Guru', href: '/guru/dashboard' },
    { title: 'File Manager', href: '/guru/file-manager' },
];

const currentType = ref(props.filters.type ?? 'all');
const currentSort = ref(props.filters.sort ?? 'created_at');
const currentDirection = ref(props.filters.direction ?? 'desc');

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let value = bytes;
    while (value >= 1024 && i < units.length - 1) {
        value /= 1024;
        i++;
    }
    return `${value.toFixed(1)} ${units[i]}`;
}

function formatDate(date: string | null): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function applyFilters() {
    router.get('/guru/file-manager', {
        type: currentType.value === 'all' ? undefined : currentType.value,
        sort: currentSort.value,
        direction: currentDirection.value,
    }, { preserveState: true });
}

function handleTypeChange(value: AcceptableValue) {
    currentType.value = String(value ?? 'all');
    applyFilters();
}

function handleSortChange(value: AcceptableValue) {
    currentSort.value = String(value ?? 'created_at');
    applyFilters();
}

function toggleDirection() {
    currentDirection.value = currentDirection.value === 'desc' ? 'asc' : 'desc';
    applyFilters();
}

function handleDelete(file: GuruFile) {
    if (file.is_used) return;
    router.delete(`/guru/file-manager/${file.type}/${file.id}`);
}

const totalSize = computed(() => props.files.reduce((sum, f) => sum + f.size, 0));
const totalFiles = computed(() => props.files.length);

const typeOptions = [
    { value: 'all', label: 'Semua Tipe' },
    { value: 'material', label: 'Materi' },
    { value: 'assignment', label: 'Tugas' },
    { value: 'question', label: 'Soal' },
];

const sortOptions = [
    { value: 'created_at', label: 'Tanggal' },
    { value: 'size', label: 'Ukuran' },
    { value: 'name', label: 'Nama' },
];
</script>

<template>
    <Head title="File Manager" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <PageHeader title="File Manager" description="Kelola file Anda" :icon="FolderOpen" />

            <!-- Summary -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <StatsCard title="Total File" :value="totalFiles" :icon="File" />
                <StatsCard title="Total Ukuran" :value="formatBytes(totalSize)" :icon="FolderOpen" />
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <Select :model-value="currentType" @update:model-value="handleTypeChange">
                    <SelectTrigger class="w-[160px]">
                        <SelectValue placeholder="Filter tipe" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="opt in typeOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select :model-value="currentSort" @update:model-value="handleSortChange">
                    <SelectTrigger class="w-[140px]">
                        <ArrowDownAZ class="mr-2 h-4 w-4" />
                        <SelectValue placeholder="Urutkan" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="opt in sortOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Button variant="outline" size="sm" @click="toggleDirection">
                    <ArrowUpDown class="mr-1 h-4 w-4" />
                    {{ currentDirection === 'desc' ? 'Terbaru' : 'Terlama' }}
                </Button>
            </div>

            <div v-if="files.length > 0" class="overflow-hidden rounded-lg border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Nama File</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Tipe</TableHead>
                            <TableHead class="text-right text-xs font-semibold uppercase tracking-wider">Ukuran</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Status</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Tanggal</TableHead>
                            <TableHead class="w-[60px] text-xs font-semibold uppercase tracking-wider" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="file in files" :key="`${file.type}-${file.id}`" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
                            <TableCell class="max-w-xs truncate font-medium">{{ file.name }}</TableCell>
                            <TableCell>
                                <Badge variant="secondary">{{ file.type_label }}</Badge>
                            </TableCell>
                            <TableCell class="text-right">{{ formatBytes(file.size) }}</TableCell>
                            <TableCell>
                                <Badge :variant="file.is_used ? 'default' : 'outline'">
                                    {{ file.usage_info }}
                                </Badge>
                            </TableCell>
                            <TableCell>{{ formatDate(file.created_at) }}</TableCell>
                            <TableCell class="text-right">
                                <ConfirmDialog
                                    v-if="!file.is_used"
                                    :title="`Hapus file &quot;${file.name}&quot;?`"
                                    description="Tindakan ini tidak dapat dibatalkan."
                                    confirm-label="Hapus"
                                    variant="destructive"
                                    @confirm="handleDelete(file)"
                                >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </ConfirmDialog>
                                <span v-else class="text-muted-foreground text-xs">Sedang dipakai</span>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <EmptyState
                v-else
                :icon="FolderOpen"
                title="Tidak ada file ditemukan"
                description="Belum ada file yang Anda upload."
            />
        </div>
    </AppLayout>
</template>
