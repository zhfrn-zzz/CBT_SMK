<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTableToolbar from '@/Components/DataTableToolbar.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
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
import { Eye, MoreHorizontal, Pencil, Plus, School, Trash2 } from 'lucide-vue-next';
import type { AcademicYear, BreadcrumbItem, Classroom, Department, PaginatedData } from '@/types';

const props = defineProps<{
    classrooms: PaginatedData<Classroom>;
    filters: { academic_year_id?: string; department_id?: string };
    academicYears: Pick<AcademicYear, 'id' | 'name' | 'semester'>[];
    departments: Pick<Department, 'id' | 'name' | 'code'>[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Kelas', href: '/admin/classrooms' },
];

const ayFilter = ref(props.filters.academic_year_id ?? 'all');
const deptFilter = ref(props.filters.department_id ?? 'all');

function applyFilters() {
    router.get(
        '/admin/classrooms',
        {
            academic_year_id: ayFilter.value === 'all' ? undefined : ayFilter.value,
            department_id: deptFilter.value === 'all' ? undefined : deptFilter.value,
        },
        { preserveState: true, replace: true },
    );
}

watch([ayFilter, deptFilter], () => applyFilters());

function deleteItem(id: number) {
    router.delete(`/admin/classrooms/${id}`, { preserveScroll: true });
}

const gradeLevelLabel: Record<string, string> = {
    '10': 'X',
    '11': 'XI',
    '12': 'XII',
};
</script>

<template>
    <Head title="Kelas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <PageHeader title="Kelas" description="Kelola data kelas" :icon="School">
                <template #actions>
                    <Button v-if="classrooms.data.length > 0" size="sm" as-child>
                        <Link href="/admin/classrooms/create">
                            <Plus class="size-4" />
                            Tambah Kelas
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <DataTableToolbar>
                <template #filters>
                    <Select v-model="ayFilter">
                        <SelectTrigger class="w-[220px]">
                            <SelectValue placeholder="Semua Tahun Ajaran" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua Tahun Ajaran</SelectItem>
                            <SelectItem
                                v-for="ay in academicYears"
                                :key="ay.id"
                                :value="String(ay.id)"
                            >
                                {{ ay.name }} ({{ ay.semester }})
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select v-model="deptFilter">
                        <SelectTrigger class="w-[220px]">
                            <SelectValue placeholder="Semua Jurusan" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua Jurusan</SelectItem>
                            <SelectItem
                                v-for="dept in departments"
                                :key="dept.id"
                                :value="String(dept.id)"
                            >
                                {{ dept.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </template>
            </DataTableToolbar>

            <div v-if="classrooms.data.length > 0" class="overflow-x-auto rounded-lg border bg-card">
                <Table class="min-w-[600px]">
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Nama Kelas</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Tingkat</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Jurusan</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Tahun Ajaran</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Jumlah Siswa</TableHead>
                            <TableHead class="w-[80px] text-xs font-semibold uppercase tracking-wider">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="item in classrooms.data"
                            :key="item.id"
                            class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors"
                        >
                            <TableCell class="font-medium">{{ item.name }}</TableCell>
                            <TableCell>{{ gradeLevelLabel[item.grade_level] ?? item.grade_level }}</TableCell>
                            <TableCell>{{ item.department?.name ?? '-' }}</TableCell>
                            <TableCell>{{ item.academic_year?.name ?? '-' }}</TableCell>
                            <TableCell>{{ item.students_count ?? 0 }}</TableCell>
                            <TableCell>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon-sm">
                                            <MoreHorizontal class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem as-child>
                                            <Link :href="`/admin/classrooms/${item.id}`">
                                                <Eye class="mr-2 size-4" />Lihat
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem as-child>
                                            <Link :href="`/admin/classrooms/${item.id}/edit`">
                                                <Pencil class="mr-2 size-4" />Edit
                                            </Link>
                                        </DropdownMenuItem>
                                        <ConfirmDialog
                                            title="Hapus Kelas"
                                            :description="`Apakah Anda yakin ingin menghapus kelas ${item.name}?`"
                                            confirm-label="Ya, Hapus"
                                            @confirm="deleteItem(item.id)"
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
                :icon="School"
                title="Belum ada kelas"
                description="Belum ada data kelas yang tersedia saat ini."
            >
                <template #action>
                    <Button size="sm" as-child>
                        <Link href="/admin/classrooms/create">
                            <Plus class="size-4" />
                            Tambah Kelas
                        </Link>
                    </Button>
                </template>
            </EmptyState>

            <Pagination
                :links="classrooms.links"
                :from="classrooms.from"
                :to="classrooms.to"
                :total="classrooms.total"
            />
        </div>
    </AppLayout>
</template>
