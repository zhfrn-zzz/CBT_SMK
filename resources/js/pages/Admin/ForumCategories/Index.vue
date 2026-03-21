<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
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
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Edit, Plus, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem, ForumCategory } from '@/types';

const props = defineProps<{
    categories: (ForumCategory & { threads_count: number })[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Kategori Forum', href: '/admin/forum-categories' },
];

const dialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const editingCategory = ref<ForumCategory | null>(null);
const deletingId = ref<number | null>(null);

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

function confirmDelete(id: number) {
    deletingId.value = id;
    deleteDialogOpen.value = true;
}

function executeDelete() {
    if (deletingId.value) {
        form.delete(`/admin/forum-categories/${deletingId.value}`, {
            onSuccess: () => { deleteDialogOpen.value = false; },
        });
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Kategori Forum" />

        <div class="mx-auto max-w-4xl space-y-6 p-6">
            <FlashMessage />

            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Kategori Forum</h1>
                <Button @click="openCreate">
                    <Plus class="mr-2 size-4" />
                    Tambah Kategori
                </Button>
            </div>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Warna</TableHead>
                        <TableHead>Nama</TableHead>
                        <TableHead>Deskripsi</TableHead>
                        <TableHead class="text-center">Urutan</TableHead>
                        <TableHead class="text-center">Threads</TableHead>
                        <TableHead class="text-center">Status</TableHead>
                        <TableHead class="text-right">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="cat in categories" :key="cat.id">
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
                                <Button variant="ghost" size="icon" class="text-destructive" @click="confirmDelete(cat.id)">
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="!categories.length">
                        <TableCell colspan="7" class="text-center py-8 text-muted-foreground">
                            Belum ada kategori forum.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

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
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Delete Dialog -->
            <AlertDialog v-model:open="deleteDialogOpen">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Kategori</AlertDialogTitle>
                        <AlertDialogDescription>
                            Thread di kategori ini akan dipindahkan ke "Umum". Lanjutkan?
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" @click="executeDelete">Hapus</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </AppLayout>
</template>
