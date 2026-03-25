<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTableToolbar from '@/components/DataTableToolbar.vue';
import EmptyState from '@/components/EmptyState.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Pagination from '@/components/Pagination.vue';
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
import { Badge } from '@/components/ui/badge';
import { Database, Eye, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem, PaginatedData, QuestionBank, Subject } from '@/types';

const props = defineProps<{
    questionBanks: PaginatedData<QuestionBank>;
    subjects: Pick<Subject, 'id' | 'name' | 'code'>[];
    filters: { search?: string; subject_id?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Bank Soal', href: '/guru/bank-soal' },
];

const search = ref(props.filters.search ?? '');
const subjectFilter = ref(props.filters.subject_id ?? '');

let debounceTimer: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        applyFilters();
    }, 300);
});

watch(subjectFilter, () => {
    applyFilters();
});

function applyFilters() {
    router.get('/guru/bank-soal', {
        search: search.value || undefined,
        subject_id: subjectFilter.value && subjectFilter.value !== 'all' ? subjectFilter.value : undefined,
    }, { preserveState: true, replace: true });
}

function deleteItem(id: number) {
    router.delete(`/guru/bank-soal/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Bank Soal" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />

            <PageHeader title="Bank Soal" description="Kelola bank soal ujian" :icon="Database">
                <template #actions>
                    <Button size="sm" as-child>
                        <Link href="/guru/bank-soal/create">
                            <Plus class="size-4" />
                            Buat Bank Soal
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <DataTableToolbar
                v-model="search"
                search-placeholder="Cari bank soal..."
            >
                <template #filters>
                    <Select v-model="subjectFilter">
                        <SelectTrigger class="w-[220px]">
                            <SelectValue placeholder="Semua Mata Pelajaran" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua Mata Pelajaran</SelectItem>
                            <SelectItem
                                v-for="subject in subjects"
                                :key="subject.id"
                                :value="String(subject.id)"
                            >
                                {{ subject.name }} ({{ subject.code }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </template>
            </DataTableToolbar>

            <div v-if="questionBanks.data.length > 0" class="overflow-hidden rounded-xl border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Nama Bank Soal</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Mata Pelajaran</TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Jumlah Soal</TableHead>
                            <TableHead class="w-[80px] text-xs font-semibold uppercase tracking-wider">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="bank in questionBanks.data"
                            :key="bank.id"
                            class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors"
                        >
                            <TableCell>
                                <div>
                                    <p class="font-medium">{{ bank.name }}</p>
                                    <p v-if="bank.description" class="text-sm text-muted-foreground line-clamp-1">
                                        {{ bank.description }}
                                    </p>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline">
                                    {{ bank.subject?.name }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-center">
                                {{ bank.questions_count ?? 0 }}
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
                                            <Link :href="`/guru/bank-soal/${bank.id}`">
                                                <Eye class="mr-2 size-4" />Lihat
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem as-child>
                                            <Link :href="`/guru/bank-soal/${bank.id}/edit`">
                                                <Pencil class="mr-2 size-4" />Edit
                                            </Link>
                                        </DropdownMenuItem>
                                        <ConfirmDialog
                                            @confirm="deleteItem(bank.id)"
                                            :description="`Apakah Anda yakin ingin menghapus ${bank.name}? Semua soal di dalam bank ini akan ikut terhapus.`"
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
                :icon="Database"
                title="Belum ada bank soal"
                description="Buat bank soal pertama Anda untuk mulai menambahkan soal ujian."
            >
                <template #action>
                    <Button size="sm" as-child>
                        <Link href="/guru/bank-soal/create">
                            <Plus class="size-4" />
                            Buat Bank Soal
                        </Link>
                    </Button>
                </template>
            </EmptyState>

            <Pagination
                :links="questionBanks.links"
                :from="questionBanks.from"
                :to="questionBanks.to"
                :total="questionBanks.total"
            />
        </div>
    </AppLayout>
</template>
