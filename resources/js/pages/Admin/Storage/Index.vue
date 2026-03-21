<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
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
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { AlertTriangle, FileWarning, HardDrive, Loader2, RefreshCw, Trash2 } from 'lucide-vue-next';
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
    if (!confirm('Apakah Anda yakin ingin menghapus semua file orphan? Tindakan ini tidak dapat dibatalkan.')) return;
    isCleaningUp.value = true;
    router.post('/admin/storage/cleanup', {}, {
        onFinish: () => { isCleaningUp.value = false; },
    });
}

function handleScan() {
    router.get('/admin/storage/scan');
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
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Manajemen Storage</h1>
                    <p class="text-muted-foreground">Pantau penggunaan storage dan kelola file orphan.</p>
                </div>
                <Button variant="outline" @click="handleScan">
                    <RefreshCw class="mr-2 h-4 w-4" />
                    Scan Ulang
                </Button>
            </div>

            <!-- Total Storage Card -->
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2">
                        <HardDrive class="h-5 w-5" />
                        Total Storage Terpakai
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-3xl font-bold">{{ formatBytes(totalUsed) }}</div>
                </CardContent>
            </Card>

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
                            <TableRow>
                                <TableHead>Nama File</TableHead>
                                <TableHead>Kategori</TableHead>
                                <TableHead class="text-right">Ukuran</TableHead>
                                <TableHead>Tanggal</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(file, idx) in topFiles" :key="idx">
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
                        <Button
                            v-if="orphanedFiles.length > 0"
                            variant="destructive"
                            :disabled="isCleaningUp"
                            @click="handleCleanup"
                        >
                            <Loader2 v-if="isCleaningUp" class="mr-2 h-4 w-4 animate-spin" />
                            <Trash2 v-else class="mr-2 h-4 w-4" />
                            Cleanup ({{ formatBytes(totalOrphanSize) }})
                        </Button>
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
                                <TableRow>
                                    <TableHead>Path</TableHead>
                                    <TableHead class="text-right">Ukuran</TableHead>
                                    <TableHead>Terakhir Dimodifikasi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(file, idx) in orphanedFiles" :key="idx">
                                    <TableCell class="max-w-sm truncate font-mono text-sm">{{ file.path }}</TableCell>
                                    <TableCell class="text-right">{{ formatBytes(file.size) }}</TableCell>
                                    <TableCell>{{ formatDate(file.last_modified) }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                    <p v-else class="text-muted-foreground text-sm">
                        ✅ Tidak ada file orphan. Storage sudah bersih.
                    </p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
