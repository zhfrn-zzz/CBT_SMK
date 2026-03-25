<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Eye, Trophy } from 'lucide-vue-next';
import type { BreadcrumbItem, SiswaResult } from '@/types';

defineProps<{
    results: SiswaResult[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/siswa/dashboard' },
    { title: 'Nilai', href: '/siswa/nilai' },
];

function formatDate(date: string | null) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Nilai Ujian" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <PageHeader title="Nilai" description="Rekap nilai ujian" :icon="Trophy" />

            <EmptyState v-if="results.length === 0" :icon="Trophy" title="Belum ada nilai" description="Belum ada hasil ujian yang dipublikasikan." />
            <div v-else class="overflow-hidden rounded-xl border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">No</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Nama Ujian</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Mata Pelajaran</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Nilai</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">KKM</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Status</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Tanggal</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(result, index) in results" :key="result.id" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
                            <TableCell>{{ index + 1 }}</TableCell>
                            <TableCell class="font-medium">{{ result.exam_name }}</TableCell>
                            <TableCell>{{ result.subject }}</TableCell>
                            <TableCell class="text-center">
                                <span v-if="result.score !== null" class="font-semibold" :class="{
                                    'text-green-600': result.pass_status === 'lulus',
                                    'text-red-600': result.pass_status === 'remedial',
                                }">
                                    {{ result.score.toFixed(2) }}
                                </span>
                                <span v-else class="text-muted-foreground">Belum dinilai</span>
                            </TableCell>
                            <TableCell class="text-center">
                                {{ result.kkm ?? '-' }}
                            </TableCell>
                            <TableCell class="text-center">
                                <StatusBadge v-if="result.pass_status" :label="result.pass_status === 'lulus' ? 'Lulus' : 'Remedial'" :variant="result.pass_status === 'lulus' ? 'default' : 'destructive'" />
                                <StatusBadge v-else-if="!result.is_fully_graded" label="Proses Penilaian" variant="secondary" />
                                <span v-else class="text-muted-foreground">-</span>
                            </TableCell>
                            <TableCell class="text-center text-sm">
                                {{ formatDate(result.submitted_at) }}
                            </TableCell>
                            <TableCell class="text-center">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="`/siswa/nilai/${result.id}`">
                                        <Eye class="mr-1 size-4" />
                                        Detail
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </AppLayout>
</template>
