<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTableToolbar from '@/Components/DataTableToolbar.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import Pagination from '@/components/Pagination.vue';
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
import { Badge } from '@/components/ui/badge';
import { BookOpen, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem, PaginatedData, Subject } from '@/types';

defineProps<{
    subjects: PaginatedData<Subject>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Mata Pelajaran', href: '/admin/subjects' },
];

function deleteItem(id: number) {
    router.delete(`/admin/subjects/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Mata Pelajaran" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <PageHeader title="Mata Pelajaran" description="Kelola data mata pelajaran" :icon="BookOpen">
                <template #actions>
                    <Button v-if="subjects.data.length > 0" size="sm" as-child>
                        <Link href="/admin/subjects/create">
                            <Plus class="size-4" />
                            Tambah Mapel
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <DataTableToolbar />

            <div v-if="subjects.data.length > 0" class="overflow-x-auto rounded-lg border bg-card">
                <Table class="min-w-[500px]">
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Kode</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Nama</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Jurusan</TableHead>
                            <TableHead class="w-[80px] text-xs font-semibold uppercase tracking-wider">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="subject in subjects.data"
                            :key="subject.id"
                            class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors"
                        >
                            <TableCell class="font-mono font-medium">{{ subject.code }}</TableCell>
                            <TableCell>{{ subject.name }}</TableCell>
                            <TableCell>
                                <Badge v-if="subject.department" variant="outline">
                                    {{ subject.department.name }}
                                </Badge>
                                <span v-else class="text-muted-foreground">Umum</span>
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
                                            <Link :href="`/admin/subjects/${subject.id}/edit`">
                                                <Pencil class="mr-2 size-4" />Edit
                                            </Link>
                                        </DropdownMenuItem>
                                        <ConfirmDialog
                                            title="Hapus Mata Pelajaran"
                                            :description="`Apakah Anda yakin ingin menghapus ${subject.name}?`"
                                            confirm-label="Ya, Hapus"
                                            @confirm="deleteItem(subject.id)"
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
                :icon="BookOpen"
                title="Belum ada mata pelajaran"
                description="Belum ada data mata pelajaran yang tersedia saat ini."
            >
                <template #action>
                    <Button size="sm" as-child>
                        <Link href="/admin/subjects/create">
                            <Plus class="size-4" />
                            Tambah Mapel
                        </Link>
                    </Button>
                </template>
            </EmptyState>

            <Pagination
                :links="subjects.links"
                :from="subjects.from"
                :to="subjects.to"
                :total="subjects.total"
            />
        </div>
    </AppLayout>
</template>
