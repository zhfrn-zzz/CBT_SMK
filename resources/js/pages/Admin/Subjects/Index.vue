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
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />

            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Mata Pelajaran</h2>
                <Button size="sm" as-child>
                    <Link href="/admin/subjects/create">
                        <Plus class="size-4" />
                        Tambah Mapel
                    </Link>
                </Button>
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Kode</TableHead>
                            <TableHead>Nama</TableHead>
                            <TableHead>Jurusan</TableHead>
                            <TableHead class="w-[100px]">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="subject in subjects.data" :key="subject.id">
                            <TableCell class="font-mono font-medium">{{ subject.code }}</TableCell>
                            <TableCell>{{ subject.name }}</TableCell>
                            <TableCell>
                                <Badge v-if="subject.department" variant="outline">
                                    {{ subject.department.name }}
                                </Badge>
                                <span v-else class="text-muted-foreground">Umum</span>
                            </TableCell>
                            <TableCell>
                                <div class="flex gap-1">
                                    <Button variant="ghost" size="icon-sm" as-child>
                                        <Link :href="`/admin/subjects/${subject.id}/edit`">
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
                                                <AlertDialogTitle>Hapus Mata Pelajaran</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    Apakah Anda yakin ingin menghapus <strong>{{ subject.name }}</strong>?
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                                <AlertDialogAction
                                                    class="bg-destructive text-white hover:bg-destructive/90"
                                                    @click="deleteItem(subject.id)"
                                                >
                                                    Hapus
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="subjects.data.length === 0">
                            <TableCell :colspan="4" class="text-center text-muted-foreground">
                                Belum ada data mata pelajaran.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination
                :links="subjects.links"
                :from="subjects.from"
                :to="subjects.to"
                :total="subjects.total"
            />
        </div>
    </AppLayout>
</template>
