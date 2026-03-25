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
import { Badge } from '@/components/ui/badge';
import { CalendarDays, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import type { AcademicYear, BreadcrumbItem, PaginatedData } from '@/types';

defineProps<{
    academicYears: PaginatedData<AcademicYear>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Tahun Ajaran', href: '/admin/academic-years' },
];

function deleteItem(id: number) {
    router.delete(`/admin/academic-years/${id}`, { preserveScroll: true });
}

function formatDate(val: string): string {
    if (!val) return '-';
    return val.substring(0, 10);
}
</script>

<template>
    <Head title="Tahun Ajaran" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />

            <PageHeader title="Tahun Ajaran" description="Kelola tahun ajaran dan semester" :icon="CalendarDays">
                <template #actions>
                    <Button size="sm" as-child>
                        <Link href="/admin/academic-years/create">
                            <Plus class="size-4" />
                            Tambah Tahun Ajaran
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <div class="overflow-hidden rounded-xl border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Nama</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Semester</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Periode</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Jumlah Kelas</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Status</TableHead>
                            <TableHead class="w-[100px] text-xs font-semibold uppercase tracking-wider">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in academicYears.data" :key="item.id" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
                            <TableCell class="font-medium">{{ item.name }}</TableCell>
                            <TableCell class="capitalize">{{ item.semester }}</TableCell>
                            <TableCell>{{ formatDate(item.starts_at) }} — {{ formatDate(item.ends_at) }}</TableCell>
                            <TableCell>{{ item.classrooms_count ?? 0 }}</TableCell>
                            <TableCell>
                                <Badge :variant="item.is_active ? 'default' : 'outline'">
                                    {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
                                </Badge>
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
                                            <Link :href="`/admin/academic-years/${item.id}/edit`">
                                                <Pencil class="mr-2 size-4" />Edit
                                            </Link>
                                        </DropdownMenuItem>
                                        <ConfirmDialog
                                            @confirm="deleteItem(item.id)"
                                            :description="`Apakah Anda yakin ingin menghapus ${item.name}? Semua kelas terkait juga akan dihapus.`"
                                        >
                                            <DropdownMenuItem class="text-destructive focus:text-destructive" @select.prevent>
                                                <Trash2 class="mr-2 size-4" />Hapus
                                            </DropdownMenuItem>
                                        </ConfirmDialog>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="academicYears.data.length === 0">
                            <TableCell :colspan="6" class="p-0">
                                <EmptyState title="Tidak ada tahun ajaran" description="Belum ada data tahun ajaran." />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination
                :links="academicYears.links"
                :from="academicYears.from"
                :to="academicYears.to"
                :total="academicYears.total"
            />
        </div>
    </AppLayout>
</template>
