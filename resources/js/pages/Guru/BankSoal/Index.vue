<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { Eye, Pencil, Plus, Trash2 } from 'lucide-vue-next';
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

            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Bank Soal</h2>
                <Button size="sm" as-child>
                    <Link href="/guru/bank-soal/create">
                        <Plus class="size-4" />
                        Buat Bank Soal
                    </Link>
                </Button>
            </div>

            <div class="flex gap-3">
                <Input
                    v-model="search"
                    placeholder="Cari bank soal..."
                    class="max-w-xs"
                />
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
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nama Bank Soal</TableHead>
                            <TableHead>Mata Pelajaran</TableHead>
                            <TableHead class="text-center">Jumlah Soal</TableHead>
                            <TableHead class="w-[120px]">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="bank in questionBanks.data" :key="bank.id">
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
                                <div class="flex gap-1">
                                    <Button variant="ghost" size="icon-sm" as-child>
                                        <Link :href="`/guru/bank-soal/${bank.id}`">
                                            <Eye class="size-4" />
                                        </Link>
                                    </Button>
                                    <Button variant="ghost" size="icon-sm" as-child>
                                        <Link :href="`/guru/bank-soal/${bank.id}/edit`">
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
                                                <AlertDialogTitle>Hapus Bank Soal</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    Apakah Anda yakin ingin menghapus <strong>{{ bank.name }}</strong>?
                                                    Semua soal di dalam bank ini akan ikut terhapus.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                                <AlertDialogAction
                                                    class="bg-destructive text-white hover:bg-destructive/90"
                                                    @click="deleteItem(bank.id)"
                                                >
                                                    Hapus
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="questionBanks.data.length === 0">
                            <TableCell :colspan="4" class="text-center text-muted-foreground">
                                Belum ada bank soal. Klik "Buat Bank Soal" untuk memulai.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination
                :links="questionBanks.links"
                :from="questionBanks.from"
                :to="questionBanks.to"
                :total="questionBanks.total"
            />
        </div>
    </AppLayout>
</template>
