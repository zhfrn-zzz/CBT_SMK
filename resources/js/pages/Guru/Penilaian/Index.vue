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
import Pagination from '@/components/Pagination.vue';
import { ClipboardCheck, Eye } from 'lucide-vue-next';
import type { BreadcrumbItem, GradingExamSession, PaginatedData } from '@/types';

const props = defineProps<{
    examSessions: PaginatedData<GradingExamSession>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Penilaian', href: '/guru/grading' },
];

function formatDate(date: string) {
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <Head title="Penilaian" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Penilaian Ujian</h2>
            </div>

            <div class="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nama Ujian</TableHead>
                            <TableHead>Mata Pelajaran</TableHead>
                            <TableHead class="text-center">Peserta</TableHead>
                            <TableHead class="text-center">Dinilai</TableHead>
                            <TableHead class="text-center">Esai Belum Dinilai</TableHead>
                            <TableHead class="text-center">Publikasi</TableHead>
                            <TableHead class="text-center">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="session in examSessions.data" :key="session.id">
                            <TableCell class="font-medium">{{ session.name }}</TableCell>
                            <TableCell>{{ session.subject?.name }}</TableCell>
                            <TableCell class="text-center">{{ session.total_attempts }}</TableCell>
                            <TableCell class="text-center">
                                <Badge :variant="session.graded_attempts === session.total_attempts && session.total_attempts > 0 ? 'default' : 'secondary'">
                                    {{ session.graded_attempts }}/{{ session.total_attempts }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge v-if="session.ungraded_essays > 0" variant="destructive">
                                    {{ session.ungraded_essays }}
                                </Badge>
                                <span v-else class="text-muted-foreground">-</span>
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge :variant="session.is_results_published ? 'default' : 'outline'">
                                    {{ session.is_results_published ? 'Dipublikasi' : 'Belum' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-center">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="`/guru/grading/${session.id}`">
                                        <Eye class="mr-1 size-4" />
                                        Lihat
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="examSessions.data.length === 0">
                            <TableCell colspan="7" class="text-center text-muted-foreground py-8">
                                Belum ada ujian yang perlu dinilai.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination :links="examSessions.links" :from="examSessions.from" :to="examSessions.to" :total="examSessions.total" />
        </div>
    </AppLayout>
</template>
