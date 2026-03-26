<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Award, Eye, MoreHorizontal } from 'lucide-vue-next';
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
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <PageHeader title="Penilaian" description="Kelola penilaian ujian" :icon="Award" />

            <div class="overflow-x-auto rounded-lg border bg-card">
                <Table class="min-w-[800px]">
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Nama Ujian</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Mata Pelajaran</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Peserta</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Dinilai</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Esai Belum Dinilai</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Publikasi</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="session in examSessions.data" :key="session.id" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
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
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon-sm"><MoreHorizontal class="size-4" /></Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem as-child>
                                            <Link :href="`/guru/grading/${session.id}`"><Eye class="mr-2 size-4" />Lihat</Link>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <EmptyState
                v-if="examSessions.data.length === 0"
                :icon="Award"
                title="Belum ada penilaian"
                description="Belum ada ujian yang perlu dinilai."
            />

            <Pagination :links="examSessions.links" :from="examSessions.from" :to="examSessions.to" :total="examSessions.total" />
        </div>
    </AppLayout>
</template>
