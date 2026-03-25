<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import Pagination from '@/components/Pagination.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
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
import { Building2, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem, Department, PaginatedData } from '@/types';

defineProps<{
    departments: PaginatedData<Department>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Jurusan', href: '/admin/departments' },
];

function deleteItem(id: number) {
    router.delete(`/admin/departments/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Jurusan" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />

            <PageHeader title="Jurusan" description="Kelola data jurusan" :icon="Building2">
                <template #actions>
                    <Button size="sm" as-child>
                        <Link href="/admin/departments/create">
                            <Plus class="size-4" />
                            Tambah Jurusan
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <div class="overflow-hidden rounded-xl border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Kode</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Nama Jurusan</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Jumlah Kelas</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Jumlah Mapel</TableHead>
                            <TableHead class="w-[100px] text-xs font-semibold uppercase tracking-wider">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="dept in departments.data" :key="dept.id" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
                            <TableCell class="font-mono font-medium">{{ dept.code }}</TableCell>
                            <TableCell>{{ dept.name }}</TableCell>
                            <TableCell>{{ dept.classrooms_count ?? 0 }}</TableCell>
                            <TableCell>{{ dept.subjects_count ?? 0 }}</TableCell>
                            <TableCell>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon-sm">
                                            <MoreHorizontal class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem as-child>
                                            <Link :href="`/admin/departments/${dept.id}/edit`">
                                                <Pencil class="mr-2 size-4" />Edit
                                            </Link>
                                        </DropdownMenuItem>
                                        <ConfirmDialog
                                            @confirm="deleteItem(dept.id)"
                                            :description="`Apakah Anda yakin ingin menghapus jurusan ${dept.name}?`"
                                        >
                                            <DropdownMenuItem class="text-destructive focus:text-destructive" @select.prevent>
                                                <Trash2 class="mr-2 size-4" />Hapus
                                            </DropdownMenuItem>
                                        </ConfirmDialog>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="departments.data.length === 0">
                            <TableCell :colspan="5" class="p-0">
                                <EmptyState title="Tidak ada jurusan" description="Belum ada data jurusan." />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination
                :links="departments.links"
                :from="departments.from"
                :to="departments.to"
                :total="departments.total"
            />
        </div>
    </AppLayout>
</template>
