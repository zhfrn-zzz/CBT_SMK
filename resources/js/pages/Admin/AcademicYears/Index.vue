<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
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
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
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

            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Tahun Ajaran</h2>
                <Button size="sm" as-child>
                    <Link href="/admin/academic-years/create">
                        <Plus class="size-4" />
                        Tambah Tahun Ajaran
                    </Link>
                </Button>
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nama</TableHead>
                            <TableHead>Semester</TableHead>
                            <TableHead>Periode</TableHead>
                            <TableHead>Jumlah Kelas</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="w-[100px]">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in academicYears.data" :key="item.id">
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
                                <div class="flex gap-1">
                                    <Button variant="ghost" size="icon-sm" as-child>
                                        <Link :href="`/admin/academic-years/${item.id}/edit`">
                                            <Pencil class="size-4" />
                                        </Link>
                                    </Button>
                                    <AlertDialog>
                                        <AlertDialogTrigger as-child>
                                            <Button variant="ghost" size="icon-sm">
                                                <Trash2 class="size-4 text-destructive" />
                                            </Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>Hapus Tahun Ajaran</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    Apakah Anda yakin ingin menghapus <strong>{{ item.name }}</strong>? Semua kelas terkait juga akan dihapus.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                                <AlertDialogAction
                                                    class="bg-destructive text-white hover:bg-destructive/90"
                                                    @click="deleteItem(item.id)"
                                                >
                                                    Hapus
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="academicYears.data.length === 0">
                            <TableCell :colspan="6" class="text-center text-muted-foreground">
                                Belum ada data tahun ajaran.
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
