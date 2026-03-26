<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatsCard from '@/Components/StatsCard.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import LoadingButton from '@/Components/LoadingButton.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { AlertTriangle, FileWarning, HardDrive, RefreshCw, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem, OrphanedFile, StorageCategoryBreakdown, StorageTopFile } from '@/types';
import { ref } from 'vue';

const props = defineProps<{
    totalUsed: number;
    breakdown: StorageCategoryBreakdown[];
    topFiles: StorageTopFile[];
    orphanedFiles: OrphanedFile[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard Admin', href: '/admin/dashboard' },
    { title: 'Manajemen Storage', href: '/admin/storage' },
];

const isScanning = ref(false);
const isCleaningUp = ref(false);

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

function handleCleanup() {
    isCleaningUp.value = true;
    router.post('/admin/storage/cleanup', {}, {
        onFinish: () => { isCleaningUp.value = false; },
    });
}

function handleScan() {
    isScanning.value = true;
    router.get('/admin/storage/scan', {}, {
        onFinish: () => { isScanning.value = false; },
    });
}

const totalOrphanSize = props.orphanedFiles.reduce((sum, f) => sum + f.size, 0);

const categoryColors: Record<string, string> = {
    materials: 'bg-blue-500',
    questions: 'bg-amber-500',
    assignments: 'bg-green-500',
    submissions: 'bg-purple-500',
};
</script>

<template>
    <Head title="Manajemen Storage" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- Header -->
            <PageHeader title="Penyimpanan" description="Kelola ruang penyimpanan server" :icon="HardDrive">
                <template #actions>
                    <LoadingButton variant="outline" :loading="isScanning" @click="handleScan">
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Scan Ulang
                    </LoadingButton>
                </template>
            </PageHeader>

            <!-- Storage Stats -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatsCard
                    title="Total Storage Terpakai"
                    :value="formatBytes(totalUsed)"
                    :icon="HardDrive"
                />
                <StatsCard
                    v-for="cat in breakdown"
                    :key="cat.category"
                    :title="cat.label"
                    :value="formatBytes(cat.size)"
                    :icon="HardDrive"
                    :iconColor="cat.category === 'materials' ? 'bg-blue-100 text-blue-600' : cat.category === 'questions' ? 'bg-amber-100 text-amber-600' : cat.category === 'assignments' ? 'bg-green-100 text-green-600' : 'bg-purple-100 text-purple-600'"
                />
            </div>

            <!-- Breakdown per Category -->
            <Card>
                <CardHeader>
                    <CardTitle>Breakdown per Kategori</CardTitle>
                    <CardDescription>Penggunaan storage berdasarkan tipe file.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-for="cat in breakdown" :key="cat.category" class="space-y-2">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium">{{ cat.label }}</span>
                                <span class="text-muted-foreground">{{ cat.count }} file — {{ formatBytes(cat.size) }}</span>
                            </div>
                            <div class="bg-muted h-2 w-full overflow-hidden rounded-full">
                                <div
                                    :class="[categoryColors[cat.category] ?? 'bg-primary', 'h-full rounded-full transition-all']"
                                    :style="{ width: totalUsed > 0 ? `${Math.max((cat.size / totalUsed) * 100, 1)}%` : '0%' }"
                                />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Top 10 Files -->
            <Card>
                <CardHeader>
                    <CardTitle>Top 10 File Terbesar</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table v-if="topFiles.length > 0">
                        <TableHeader>
                            <TableRow class="bg-slate-50 hover:bg-slate-50 dark:bg-slate-800/50">
                                <TableHead class="text-xs font-medium uppercase tracking-wider">Nama File</TableHead>
                                <TableHead class="text-xs font-medium uppercase tracking-wider">Kategori</TableHead>
                                <TableHead class="text-right text-xs font-medium uppercase tracking-wider">Ukuran</TableHead>
                                <TableHead class="text-xs font-medium uppercase tracking-wider">Tanggal</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(file, idx) in topFiles" :key="idx" class="transition-colors hover:bg-muted/50" :class="idx % 2 === 1 ? 'bg-muted/30' : ''">
                                <TableCell class="max-w-xs truncate font-medium">{{ file.name }}</TableCell>
                                <TableCell>
                                    <Badge variant="secondary">{{ file.category }}</Badge>
                                </TableCell>
                                <TableCell class="text-right">{{ formatBytes(file.size) }}</TableCell>
                                <TableCell>{{ formatDate(file.created_at) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <p v-else class="text-muted-foreground text-sm">Tidak ada file yang ditemukan.</p>
                </CardContent>
            </Card>

            <!-- Orphaned Files -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <FileWarning class="h-5 w-5 text-amber-500" />
                                File Orphan
                            </CardTitle>
                            <CardDescription>
                                File yang ada di disk tetapi tidak terkait dengan record database.
                            </CardDescription>
                        </div>
                        <ConfirmDialog
                            title="Cleanup File Orphan"
                            description="Apakah Anda yakin ingin menghapus semua file orphan? Tindakan ini tidak dapat dibatalkan."
                            confirmLabel="Ya, Hapus Semua"
                            @confirm="handleCleanup"
                        >
                            <LoadingButton
                                v-if="orphanedFiles.length > 0"
                                variant="destructive"
                                :loading="isCleaningUp"
                            >
                                <Trash2 class="mr-2 h-4 w-4" />
                                Cleanup ({{ formatBytes(totalOrphanSize) }})
                            </LoadingButton>
                        </ConfirmDialog>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="orphanedFiles.length > 0">
                        <div class="bg-amber-50 dark:bg-amber-950/20 mb-4 flex items-start gap-2 rounded-lg border border-amber-200 p-3 dark:border-amber-800">
                            <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                            <p class="text-sm text-amber-700 dark:text-amber-400">
                                Ditemukan {{ orphanedFiles.length }} file orphan ({{ formatBytes(totalOrphanSize) }}).
                                Klik "Cleanup" untuk menghapus file-file ini via background job.
                            </p>
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow class="bg-slate-50 hover:bg-slate-50 dark:bg-slate-800/50">
                                    <TableHead class="text-xs font-medium uppercase tracking-wider">Path</TableHead>
                                    <TableHead class="text-right text-xs font-medium uppercase tracking-wider">Ukuran</TableHead>
                                    <TableHead class="text-xs font-medium uppercase tracking-wider">Terakhir Dimodifikasi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(file, idx) in orphanedFiles" :key="idx" class="transition-colors hover:bg-muted/50" :class="idx % 2 === 1 ? 'bg-muted/30' : ''">
                                    <TableCell class="max-w-sm truncate font-mono text-sm">{{ file.path }}</TableCell>
                                    <TableCell class="text-right">{{ formatBytes(file.size) }}</TableCell>
                                    <TableCell>{{ formatDate(file.last_modified) }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                    <p v-else class="text-muted-foreground text-sm">
                    Tidak ada file orphan. Storage sudah bersih.
                    </p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
