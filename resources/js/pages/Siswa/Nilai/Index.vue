<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Eye } from 'lucide-vue-next';
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
            <h2 class="text-xl font-semibold">Nilai Ujian</h2>

            <div class="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>No</TableHead>
                            <TableHead>Nama Ujian</TableHead>
                            <TableHead>Mata Pelajaran</TableHead>
                            <TableHead class="text-center">Nilai</TableHead>
                            <TableHead class="text-center">KKM</TableHead>
                            <TableHead class="text-center">Status</TableHead>
                            <TableHead class="text-center">Tanggal</TableHead>
                            <TableHead class="text-center">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(result, index) in results" :key="result.id">
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
                                <Badge v-if="result.pass_status" :variant="result.pass_status === 'lulus' ? 'default' : 'destructive'">
                                    {{ result.pass_status === 'lulus' ? 'Lulus' : 'Remedial' }}
                                </Badge>
                                <Badge v-else-if="!result.is_fully_graded" variant="secondary">
                                    Proses Penilaian
                                </Badge>
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
                        <TableRow v-if="results.length === 0">
                            <TableCell colspan="8" class="text-center text-muted-foreground py-8">
                                Belum ada hasil ujian yang dipublikasikan.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </AppLayout>
</template>
