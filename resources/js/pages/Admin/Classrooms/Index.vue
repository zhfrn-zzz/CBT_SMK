<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
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
import { Eye, Pencil, Plus, Trash2 } from 'lucide-vue-next';
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
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />

            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Kelas</h2>
                <Button size="sm" as-child>
                    <Link href="/admin/classrooms/create">
                        <Plus class="size-4" />
                        Tambah Kelas
                    </Link>
                </Button>
            </div>

            <div class="flex gap-3">
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
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nama Kelas</TableHead>
                            <TableHead>Tingkat</TableHead>
                            <TableHead>Jurusan</TableHead>
                            <TableHead>Tahun Ajaran</TableHead>
                            <TableHead>Jumlah Siswa</TableHead>
                            <TableHead class="w-[130px]">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in classrooms.data" :key="item.id">
                            <TableCell class="font-medium">{{ item.name }}</TableCell>
                            <TableCell>{{ gradeLevelLabel[item.grade_level] ?? item.grade_level }}</TableCell>
                            <TableCell>{{ item.department?.name ?? '-' }}</TableCell>
                            <TableCell>{{ item.academic_year?.name ?? '-' }}</TableCell>
                            <TableCell>{{ item.students_count ?? 0 }}</TableCell>
                            <TableCell>
                                <div class="flex gap-1">
                                    <Button variant="ghost" size="icon-sm" as-child>
                                        <Link :href="`/admin/classrooms/${item.id}`">
                                            <Eye class="size-4" />
                                        </Link>
                                    </Button>
                                    <Button variant="ghost" size="icon-sm" as-child>
                                        <Link :href="`/admin/classrooms/${item.id}/edit`">
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
                                                <AlertDialogTitle>Hapus Kelas</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    Apakah Anda yakin ingin menghapus kelas <strong>{{ item.name }}</strong>?
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                                <AlertDialogAction
                                                    class="bg-destructive text-white hover:bg-destructive/90"
                                                    @click="deleteItem(item.id)"
                                                >
                                                    Hapus
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="classrooms.data.length === 0">
                            <TableCell :colspan="6" class="text-center text-muted-foreground">
                                Belum ada data kelas.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination
                :links="classrooms.links"
                :from="classrooms.from"
                :to="classrooms.to"
                :total="classrooms.total"
            />
        </div>
    </AppLayout>
</template>
