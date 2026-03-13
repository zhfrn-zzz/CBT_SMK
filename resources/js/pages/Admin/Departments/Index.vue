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
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
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

            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Jurusan</h2>
                <Button size="sm" as-child>
                    <Link href="/admin/departments/create">
                        <Plus class="size-4" />
                        Tambah Jurusan
                    </Link>
                </Button>
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Kode</TableHead>
                            <TableHead>Nama Jurusan</TableHead>
                            <TableHead>Jumlah Kelas</TableHead>
                            <TableHead>Jumlah Mapel</TableHead>
                            <TableHead class="w-[100px]">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="dept in departments.data" :key="dept.id">
                            <TableCell class="font-mono font-medium">{{ dept.code }}</TableCell>
                            <TableCell>{{ dept.name }}</TableCell>
                            <TableCell>{{ dept.classrooms_count ?? 0 }}</TableCell>
                            <TableCell>{{ dept.subjects_count ?? 0 }}</TableCell>
                            <TableCell>
                                <div class="flex gap-1">
                                    <Button variant="ghost" size="icon-sm" as-child>
                                        <Link :href="`/admin/departments/${dept.id}/edit`">
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
                                                <AlertDialogTitle>Hapus Jurusan</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    Apakah Anda yakin ingin menghapus jurusan <strong>{{ dept.name }}</strong>?
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                                <AlertDialogAction
                                                    class="bg-destructive text-white hover:bg-destructive/90"
                                                    @click="deleteItem(dept.id)"
                                                >
                                                    Hapus
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="departments.data.length === 0">
                            <TableCell :colspan="5" class="text-center text-muted-foreground">
                                Belum ada data jurusan.
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
