<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import Pagination from '@/components/Pagination.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import DataTableToolbar from '@/Components/DataTableToolbar.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { GraduationCap, MoreHorizontal, Pencil, Plus, Trash2, Upload, KeyRound, Copy, Check } from 'lucide-vue-next';
import type { BreadcrumbItem, PaginatedData, Subject, User } from '@/types';

const props = defineProps<{
    gurus: PaginatedData<User>;
    filters: { search?: string; subject_id?: string; department_id?: string };
    subjects: Subject[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Data Guru', href: '/admin/guru' },
];

const search = ref(props.filters.search ?? '');
const subjectFilter = ref(props.filters.subject_id ?? 'all');

let searchTimeout: ReturnType<typeof setTimeout>;

function applyFilters() {
    router.get(
        '/admin/guru',
        {
            search: search.value || undefined,
            subject_id: subjectFilter.value === 'all' ? undefined : subjectFilter.value,
        },
        { preserveState: true, replace: true },
    );
}

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

watch(subjectFilter, applyFilters);

function deleteGuru(guruId: number) {
    router.delete(`/admin/guru/${guruId}`, { preserveScroll: true });
}

// Reset Password
const showResetModal = ref(false);
const resetResult = ref<{ name: string; username: string; password: string } | null>(null);
const resetLoading = ref(false);
const resetCopied = ref(false);
const confirmResetUserId = ref<number | null>(null);
const confirmResetUserName = ref('');

function confirmResetPassword(guru: User) {
    confirmResetUserId.value = guru.id;
    confirmResetUserName.value = guru.name;
}

function doResetPassword() {
    if (!confirmResetUserId.value) return;
    resetLoading.value = true;

    fetch(`/admin/guru/${confirmResetUserId.value}/reset-password`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
        .then(res => res.json())
        .then(data => {
            resetResult.value = data;
            showResetModal.value = true;
            confirmResetUserId.value = null;
        })
        .finally(() => { resetLoading.value = false; });
}

function copyResetPassword() {
    if (resetResult.value) {
        navigator.clipboard.writeText(resetResult.value.password);
        resetCopied.value = true;
        setTimeout(() => { resetCopied.value = false; }, 2000);
    }
}

// Import
const showImportDialog = ref(false);
const importFile = ref<File | null>(null);
const importLoading = ref(false);

function handleImportFile(e: Event) {
    const target = e.target as HTMLInputElement;
    importFile.value = target.files?.[0] ?? null;
}

function submitImport() {
    if (!importFile.value) return;
    importLoading.value = true;

    const formData = new FormData();
    formData.append('file', importFile.value);

    router.post('/admin/guru/import', formData as unknown as Record<string, unknown>, {
        forceFormData: true,
        onFinish: () => {
            importLoading.value = false;
            showImportDialog.value = false;
            importFile.value = null;
        },
    });
}
</script>

<template>
    <Head title="Data Guru" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <PageHeader title="Data Guru" :icon="GraduationCap">
                <template #actions>
                    <Button variant="outline" size="sm" @click="showImportDialog = true">
                        <Upload class="size-4 mr-1" />Import Guru
                    </Button>
                    <Button size="sm" as-child>
                        <Link href="/admin/guru/create">
                            <Plus class="size-4 mr-1" />Tambah Guru
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <!-- Filters -->
            <DataTableToolbar v-model:search="search" placeholder="Cari nama, NIP, atau email...">
                <Select v-model="subjectFilter">
                    <SelectTrigger class="w-[180px]">
                        <SelectValue placeholder="Semua Mapel" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua Mapel</SelectItem>
                        <SelectItem v-for="subject in subjects" :key="subject.id" :value="String(subject.id)">
                            {{ subject.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </DataTableToolbar>

            <!-- Table -->
            <div class="overflow-x-auto rounded-lg border bg-card">
                <Table class="min-w-[800px]">
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Nama</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">NIP</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Email</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Telepon</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Mengajar</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider text-center">Status</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider text-right">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="guru in gurus.data" :key="guru.id" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
                            <TableCell class="font-medium">{{ guru.name }}</TableCell>
                            <TableCell class="font-mono text-sm">{{ guru.username }}</TableCell>
                            <TableCell>{{ guru.email ?? '-' }}</TableCell>
                            <TableCell>{{ (guru as Record<string, unknown>).phone ?? '-' }}</TableCell>
                            <TableCell>
                                <div class="flex flex-wrap gap-1">
                                    <Badge v-for="(t, i) in (guru.teachings ?? []).slice(0, 3)" :key="i" variant="outline" class="text-xs">
                                        {{ t.classroom_name }}·{{ t.subject_name }}
                                    </Badge>
                                    <Badge v-if="(guru.teachings ?? []).length > 3" variant="secondary" class="text-xs">
                                        +{{ (guru.teachings ?? []).length - 3 }}
                                    </Badge>
                                    <span v-if="!(guru.teachings ?? []).length" class="text-muted-foreground text-xs">-</span>
                                </div>
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge :variant="guru.is_active ? 'default' : 'destructive'">
                                    {{ guru.is_active ? 'Aktif' : 'Nonaktif' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon-sm">
                                            <MoreHorizontal class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem as-child>
                                            <Link :href="`/admin/guru/${guru.id}/edit`">
                                                <Pencil class="size-4 mr-2" />Edit
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click="confirmResetPassword(guru)">
                                            <KeyRound class="size-4 mr-2" />Reset Password
                                        </DropdownMenuItem>
                                        <ConfirmDialog
                                            title="Hapus Guru"
                                            :description="`Yakin ingin menghapus guru '${guru.name}'? Tindakan ini tidak dapat dibatalkan.`"
                                            confirm-label="Hapus"
                                            variant="destructive"
                                            @confirm="deleteGuru(guru.id)"
                                        >
                                            <DropdownMenuItem @select.prevent>
                                                <Trash2 class="size-4 mr-2" />Hapus
                                            </DropdownMenuItem>
                                        </ConfirmDialog>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="gurus.data.length === 0">
                            <TableCell :colspan="7" class="p-0">
                                <EmptyState title="Tidak ada guru" description="Belum ada data guru yang terdaftar." />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination :links="gurus.links" :from="gurus.from" :to="gurus.to" :total="gurus.total" />
        </div>

        <!-- Reset Password Confirm Dialog -->
        <ConfirmDialog
            :open="!!confirmResetUserId"
            title="Reset Password"
            :description="`Yakin ingin reset password guru '${confirmResetUserName}'? Password baru akan digenerate otomatis.`"
            confirm-label="Reset"
            variant="destructive"
            :loading="resetLoading"
            @confirm="doResetPassword"
            @cancel="confirmResetUserId = null"
        />

        <!-- Reset Password Result Dialog -->
        <Dialog v-model:open="showResetModal">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Password Baru</DialogTitle>
                    <DialogDescription>
                        Password untuk <strong>{{ resetResult?.name }}</strong> ({{ resetResult?.username }}) telah direset.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex items-center gap-2 p-3 bg-muted rounded-md font-mono text-lg">
                    {{ resetResult?.password }}
                    <Button variant="ghost" size="icon-sm" @click="copyResetPassword">
                        <Check v-if="resetCopied" class="size-4 text-green-500" />
                        <Copy v-else class="size-4" />
                    </Button>
                </div>
                <DialogFooter>
                    <Button @click="showResetModal = false">Tutup</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Import Dialog -->
        <Dialog v-model:open="showImportDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Import Data Guru</DialogTitle>
                    <DialogDescription>
                        Upload file Excel (.xlsx) dengan kolom: NIP, Nama, Email, Telepon.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <Label for="import-file">File Excel</Label>
                        <Input id="import-file" type="file" accept=".xlsx,.xls,.csv" @change="handleImportFile" />
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="/admin/guru/import/template" class="text-sm text-blue-600 hover:underline">
                            Download Template
                        </a>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showImportDialog = false">Batal</Button>
                    <Button :disabled="!importFile || importLoading" @click="submitImport">
                        {{ importLoading ? 'Mengimport...' : 'Import' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
