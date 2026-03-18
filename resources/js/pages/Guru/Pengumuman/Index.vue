<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { Megaphone, Pencil, Pin, Plus, Trash2 } from 'lucide-vue-next';
import type { Announcement, BreadcrumbItem, PaginatedData } from '@/types';

const props = defineProps<{
    announcements: PaginatedData<Announcement>;
    teachingAssignments: { id: number; classroom: { id: number; name: string }; subject: { id: number; name: string } }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Pengumuman', href: '/guru/pengumuman' },
];

function deleteItem(id: number) {
    router.delete(`/guru/pengumuman/${id}`, { preserveScroll: true });
}

function togglePin(id: number) {
    router.post(`/guru/pengumuman/${id}/toggle-pin`, {}, { preserveScroll: true });
}

function formatDate(d: string) {
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="Pengumuman" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Pengumuman</h2>
                <Button size="sm" as-child>
                    <Link href="/guru/pengumuman/create"><Plus class="size-4" />Buat Pengumuman</Link>
                </Button>
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Judul</TableHead>
                            <TableHead>Target</TableHead>
                            <TableHead>Tanggal Publish</TableHead>
                            <TableHead class="text-center">Pin</TableHead>
                            <TableHead>Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="a in announcements.data" :key="a.id">
                            <TableCell class="font-medium">{{ a.title }}</TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                {{ a.classroom ? a.classroom.name : 'Semua Kelas' }}
                            </TableCell>
                            <TableCell class="text-sm">{{ formatDate(a.published_at) }}</TableCell>
                            <TableCell class="text-center">
                                <Pin v-if="a.is_pinned" class="size-4 text-primary mx-auto" />
                            </TableCell>
                            <TableCell>
                                <div class="flex gap-1">
                                    <Button variant="ghost" size="icon-sm" @click="togglePin(a.id)">
                                        <Pin class="size-4" :class="a.is_pinned ? 'text-primary' : 'text-muted-foreground'" />
                                    </Button>
                                    <Button variant="ghost" size="icon-sm" as-child>
                                        <Link :href="`/guru/pengumuman/${a.id}/edit`"><Pencil class="size-4" /></Link>
                                    </Button>
                                    <AlertDialog>
                                        <AlertDialogTrigger as-child>
                                            <Button variant="ghost" size="icon-sm"><Trash2 class="size-4 text-destructive" /></Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>Hapus Pengumuman</AlertDialogTitle>
                                                <AlertDialogDescription>Pengumuman ini akan dihapus permanen.</AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                                <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="deleteItem(a.id)">Hapus</AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="announcements.data.length === 0">
                            <TableCell :colspan="5" class="text-center text-muted-foreground">Belum ada pengumuman.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination :links="announcements.links" :from="announcements.from" :to="announcements.to" :total="announcements.total" />
        </div>
    </AppLayout>
</template>
