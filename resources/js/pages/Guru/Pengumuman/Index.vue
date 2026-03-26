<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Megaphone, MoreHorizontal, Pencil, Pin, Plus, Trash2 } from 'lucide-vue-next';
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
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <PageHeader title="Pengumuman" description="Kelola pengumuman" :icon="Megaphone">
                <template #actions>
                    <Button size="sm" as-child>
                        <Link href="/guru/pengumuman/create">
                            <Plus class="size-4" />
                            Buat Pengumuman
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <div v-if="announcements.data.length > 0" class="overflow-x-auto rounded-lg border bg-card">
                <Table class="min-w-[600px]">
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Judul</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Target</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Tanggal Publish</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Pin</TableHead>
                            <TableHead class="w-[60px] text-xs font-semibold uppercase tracking-wider" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="a in announcements.data" :key="a.id" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
                            <TableCell class="font-medium">{{ a.title }}</TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                {{ a.classroom ? a.classroom.name : 'Semua Kelas' }}
                            </TableCell>
                            <TableCell class="text-sm">{{ formatDate(a.published_at) }}</TableCell>
                            <TableCell class="text-center">
                                <Pin v-if="a.is_pinned" class="size-4 text-primary mx-auto" />
                            </TableCell>
                            <TableCell>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon-sm">
                                            <MoreHorizontal class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem @click="togglePin(a.id)">
                                            <Pin class="mr-2 size-4" />{{ a.is_pinned ? 'Unpin' : 'Pin' }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem as-child>
                                            <Link :href="`/guru/pengumuman/${a.id}/edit`">
                                                <Pencil class="mr-2 size-4" />Edit
                                            </Link>
                                        </DropdownMenuItem>
                                        <ConfirmDialog
                                            title="Hapus Pengumuman"
                                            description="Pengumuman ini akan dihapus permanen."
                                            confirm-label="Hapus"
                                            variant="destructive"
                                            @confirm="deleteItem(a.id)"
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
                :icon="Megaphone"
                title="Belum ada pengumuman"
                description="Buat pengumuman pertama Anda untuk menyampaikan informasi ke siswa."
            >
                <template #action>
                    <Button size="sm" as-child>
                        <Link href="/guru/pengumuman/create">
                            <Plus class="size-4" />
                            Buat Pengumuman
                        </Link>
                    </Button>
                </template>
            </EmptyState>

            <Pagination :links="announcements.links" :from="announcements.from" :to="announcements.to" :total="announcements.total" />
        </div>
    </AppLayout>
</template>
