<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import LoadingButton from '@/Components/LoadingButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/components/ui/dialog';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Edit, MessageSquare, Plus, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem, ForumCategory } from '@/types';

const props = defineProps<{
    categories: (ForumCategory & { threads_count: number })[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Kategori Forum', href: '/admin/forum-categories' },
];

const dialogOpen = ref(false);
const editingCategory = ref<ForumCategory | null>(null);

const form = useForm({
    name: '',
    description: '',
    color: '#3b82f6',
    order: 0,
    is_active: true,
});

function openCreate() {
    editingCategory.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}

function openEdit(cat: ForumCategory) {
    editingCategory.value = cat;
    form.name = cat.name;
    form.description = cat.description ?? '';
    form.color = cat.color ?? '#3b82f6';
    form.order = cat.order;
    form.is_active = cat.is_active;
    form.clearErrors();
    dialogOpen.value = true;
}

function submit() {
    if (editingCategory.value) {
        form.put(`/admin/forum-categories/${editingCategory.value.id}`, {
            onSuccess: () => { dialogOpen.value = false; },
        });
    } else {
        form.post('/admin/forum-categories', {
            onSuccess: () => { dialogOpen.value = false; },
        });
    }
}

function executeDelete(id: number) {
    form.delete(`/admin/forum-categories/${id}`);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Kategori Forum" />

        <div class="mx-auto max-w-4xl space-y-6 p-6">
            <FlashMessage />

            <PageHeader title="Kategori Forum" description="Kelola kategori diskusi forum" :icon="MessageSquare">
                <template #actions>
                    <Button @click="openCreate">
                        <Plus class="mr-2 size-4" />
                        Tambah Kategori
                    </Button>
                </template>
            </PageHeader>

            <EmptyState
                v-if="categories.length === 0"
                :icon="MessageSquare"
                title="Belum ada kategori forum"
                description="Tambahkan kategori untuk mengorganisir diskusi forum."
            >
                <template #action>
                    <Button @click="openCreate">
                        <Plus class="mr-2 size-4" />
                        Tambah Kategori
                    </Button>
                </template>
            </EmptyState>

            <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow class="bg-slate-50 hover:bg-slate-50 dark:bg-slate-800/50">
                        <TableHead class="text-xs font-medium uppercase tracking-wider">Warna</TableHead>
                        <TableHead class="text-xs font-medium uppercase tracking-wider">Nama</TableHead>
                        <TableHead class="text-xs font-medium uppercase tracking-wider">Deskripsi</TableHead>
                        <TableHead class="text-center text-xs font-medium uppercase tracking-wider">Urutan</TableHead>
                        <TableHead class="text-center text-xs font-medium uppercase tracking-wider">Threads</TableHead>
                        <TableHead class="text-center text-xs font-medium uppercase tracking-wider">Status</TableHead>
                        <TableHead class="text-right text-xs font-medium uppercase tracking-wider">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(cat, index) in categories" :key="cat.id" class="transition-colors hover:bg-muted/50" :class="index % 2 === 1 ? 'bg-muted/30' : ''">
                        <TableCell>
                            <div class="size-6 rounded-full border" :style="{ backgroundColor: cat.color ?? '#ccc' }" />
                        </TableCell>
                        <TableCell class="font-medium">{{ cat.name }}</TableCell>
                        <TableCell class="text-sm text-muted-foreground max-w-xs truncate">{{ cat.description ?? '-' }}</TableCell>
                        <TableCell class="text-center">{{ cat.order }}</TableCell>
                        <TableCell class="text-center">{{ cat.threads_count }}</TableCell>
                        <TableCell class="text-center">
                            <Badge :variant="cat.is_active ? 'default' : 'secondary'">
                                {{ cat.is_active ? 'Aktif' : 'Nonaktif' }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-1">
                                <Button variant="ghost" size="icon" @click="openEdit(cat)">
                                    <Edit class="size-4" />
                                </Button>
                                <ConfirmDialog
                                    title="Hapus Kategori"
                                    description="Thread di kategori ini akan dipindahkan ke 'Umum'. Lanjutkan?"
                                    confirmLabel="Hapus"
                                    @confirm="executeDelete(cat.id)"
                                >
                                    <Button variant="ghost" size="icon" class="text-destructive">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </ConfirmDialog>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            </div>

            <!-- Create/Edit Dialog -->
            <Dialog v-model:open="dialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{{ editingCategory ? 'Edit Kategori' : 'Tambah Kategori' }}</DialogTitle>
                    </DialogHeader>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Nama</Label>
                            <Input id="name" v-model="form.name" />
                            <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="description">Deskripsi</Label>
                            <Textarea id="description" v-model="form.description" rows="3" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="color">Warna</Label>
                                <Input id="color" v-model="form.color" type="color" class="h-10" />
                            </div>
                            <div class="space-y-2">
                                <Label for="order">Urutan</Label>
                                <Input id="order" v-model.number="form.order" type="number" min="0" />
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Switch id="is_active" v-model:checked="form.is_active" />
                            <Label for="is_active">Aktif</Label>
                        </div>
                        <DialogFooter>
                            <LoadingButton type="submit" :loading="form.processing">
                                Simpan
                            </LoadingButton>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>


        </div>
    </AppLayout>
</template>
