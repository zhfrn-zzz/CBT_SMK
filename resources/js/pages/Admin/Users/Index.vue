<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
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
import { Badge } from '@/components/ui/badge';
import { MoreHorizontal, Pencil, Plus, Trash2, Upload, Users } from 'lucide-vue-next';
import type { BreadcrumbItem, Classroom, Department, PaginatedData, User } from '@/types';

const props = defineProps<{
    users: PaginatedData<User>;
    filters: { search?: string; role?: string; department_id?: string; classroom_id?: string };
    roles: { value: string; label: string }[];
    departments: Department[];
    classrooms: Classroom[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Manajemen Pengguna', href: '/admin/users' },
];

const search = ref(props.filters.search ?? '');
const roleFilter = ref(props.filters.role ?? 'all');
const departmentFilter = ref(props.filters.department_id ?? 'all');
const classroomFilter = ref(props.filters.classroom_id ?? 'all');

const isSiswaView = computed(() => roleFilter.value === 'siswa');
const isGuruView = computed(() => roleFilter.value === 'guru');

const filteredClassroomOptions = computed(() => {
    if (departmentFilter.value === 'all') return props.classrooms;
    return props.classrooms.filter((c) => c.department_id === Number(departmentFilter.value));
});

let searchTimeout: ReturnType<typeof setTimeout>;

function applyFilters() {
    router.get(
        '/admin/users',
        {
            search: search.value || undefined,
            role: roleFilter.value === 'all' ? undefined : roleFilter.value,
            department_id: departmentFilter.value === 'all' ? undefined : departmentFilter.value,
            classroom_id: classroomFilter.value === 'all' ? undefined : classroomFilter.value,
        },
        { preserveState: true, replace: true },
    );
}

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

watch(roleFilter, () => {
    // Reset siswa-specific filters when switching away
    if (roleFilter.value !== 'siswa') {
        departmentFilter.value = 'all';
        classroomFilter.value = 'all';
    }
    applyFilters();
});

watch(departmentFilter, () => {
    classroomFilter.value = 'all';
    applyFilters();
});

watch(classroomFilter, applyFilters);

function deleteUser(userId: number) {
    router.delete(`/admin/users/${userId}`, { preserveScroll: true });
}

const roleBadgeVariant = (role: string) => {
    switch (role) {
        case 'admin':
            return 'default';
        case 'guru':
            return 'secondary';
        default:
            return 'outline';
    }
};

const colSpan = computed(() => {
    let base = 6; // Name, Username, Email, Role, Status, Aksi
    if (isSiswaView.value) base += 2; // Kelas, Jurusan
    if (isGuruView.value) base += 1; // Mengajar
    return base;
});
</script>

<template>
    <Head title="Manajemen Pengguna" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <PageHeader title="Manajemen Pengguna" description="Kelola data pengguna sistem" :icon="Users">
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link href="/admin/users/create?import=true">
                            <Upload class="size-4" />
                            Import Siswa
                        </Link>
                    </Button>
                    <Button size="sm" as-child>
                        <Link href="/admin/users/create">
                            <Plus class="size-4" />
                            Tambah Pengguna
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <DataTableToolbar v-model="search" search-placeholder="Cari nama, username, atau email...">
                <template #filters>
                    <Select v-model="roleFilter">
                        <SelectTrigger class="w-[180px]">
                            <SelectValue placeholder="Semua Role" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua Role</SelectItem>
                            <SelectItem v-for="role in roles" :key="role.value" :value="role.value">
                                {{ role.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <template v-if="isSiswaView">
                        <Select v-model="departmentFilter">
                            <SelectTrigger class="w-[180px]">
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
                        <Select v-model="classroomFilter">
                            <SelectTrigger class="w-[180px]">
                                <SelectValue placeholder="Semua Kelas" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua Kelas</SelectItem>
                                <SelectItem
                                    v-for="cls in filteredClassroomOptions"
                                    :key="cls.id"
                                    :value="String(cls.id)"
                                >
                                    {{ cls.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </template>
                </template>
            </DataTableToolbar>

            <div class="overflow-x-auto rounded-lg border bg-card">
                <Table class="min-w-[700px]">
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Nama</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Username</TableHead>
                            <TableHead v-if="!isSiswaView && !isGuruView" class="text-xs font-semibold uppercase tracking-wider">Email</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Role</TableHead>
                            <TableHead v-if="isSiswaView" class="text-xs font-semibold uppercase tracking-wider">Jurusan</TableHead>
                            <TableHead v-if="isSiswaView" class="text-xs font-semibold uppercase tracking-wider">Kelas</TableHead>
                            <TableHead v-if="isGuruView" class="text-xs font-semibold uppercase tracking-wider">Mengajar</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Status</TableHead>
                            <TableHead class="w-[100px] text-xs font-semibold uppercase tracking-wider">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="user in users.data" :key="user.id" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
                            <TableCell class="font-medium">{{ user.name }}</TableCell>
                            <TableCell>{{ user.username }}</TableCell>
                            <TableCell v-if="!isSiswaView && !isGuruView">{{ user.email ?? '-' }}</TableCell>
                            <TableCell>
                                <Badge :variant="roleBadgeVariant(user.role)">
                                    {{ user.role }}
                                </Badge>
                            </TableCell>
                            <TableCell v-if="isSiswaView">
                                {{ user.department_name ?? '-' }}
                            </TableCell>
                            <TableCell v-if="isSiswaView">
                                {{ user.classroom_name ?? '-' }}
                            </TableCell>
                            <TableCell v-if="isGuruView">
                                <div v-if="user.teachings && user.teachings.length > 0" class="flex flex-wrap gap-1">
                                    <Badge
                                        v-for="(t, i) in user.teachings"
                                        :key="i"
                                        variant="outline"
                                        class="text-xs"
                                    >
                                        {{ t.subject_name }} &middot; {{ t.classroom_name }}
                                    </Badge>
                                </div>
                                <span v-else class="text-muted-foreground">-</span>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="user.is_active ? 'default' : 'destructive'">
                                    {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                                </Badge>
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
                                            <Link :href="`/admin/users/${user.id}/edit`">
                                                <Pencil class="mr-2 size-4" />Edit
                                            </Link>
                                        </DropdownMenuItem>
                                        <ConfirmDialog
                                            @confirm="deleteUser(user.id)"
                                            :description="`Apakah Anda yakin ingin menghapus ${user.name}? Tindakan ini tidak dapat dibatalkan.`"
                                        >
                                            <DropdownMenuItem class="text-destructive focus:text-destructive" @select.prevent>
                                                <Trash2 class="mr-2 size-4" />Hapus
                                            </DropdownMenuItem>
                                        </ConfirmDialog>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="users.data.length === 0">
                            <TableCell :colspan="colSpan" class="p-0">
                                <EmptyState title="Tidak ada pengguna" description="Belum ada data pengguna yang sesuai filter." />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination
                :links="users.links"
                :from="users.from"
                :to="users.to"
                :total="users.total"
            />
        </div>
    </AppLayout>
</template>
