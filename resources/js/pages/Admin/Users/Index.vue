<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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
import { Pencil, Plus, Trash2, Upload } from 'lucide-vue-next';
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
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />

            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Manajemen Pengguna</h2>
                <div class="flex gap-2">
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
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <Input
                    v-model="search"
                    placeholder="Cari nama, username, atau email..."
                    class="max-w-sm"
                />
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

                <!-- Siswa filters: Jurusan & Kelas -->
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
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nama</TableHead>
                            <TableHead>Username</TableHead>
                            <TableHead v-if="!isSiswaView && !isGuruView">Email</TableHead>
                            <TableHead>Role</TableHead>
                            <TableHead v-if="isSiswaView">Jurusan</TableHead>
                            <TableHead v-if="isSiswaView">Kelas</TableHead>
                            <TableHead v-if="isGuruView">Mengajar</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="w-[100px]">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="user in users.data" :key="user.id">
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
                                <div class="flex gap-1">
                                    <Button variant="ghost" size="icon-sm" as-child>
                                        <Link :href="`/admin/users/${user.id}/edit`">
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
                                                <AlertDialogTitle>Hapus Pengguna</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    Apakah Anda yakin ingin menghapus <strong>{{ user.name }}</strong>? Tindakan ini tidak dapat dibatalkan.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                                <AlertDialogAction
                                                    class="bg-destructive text-white hover:bg-destructive/90"
                                                    @click="deleteUser(user.id)"
                                                >
                                                    Hapus
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="users.data.length === 0">
                            <TableCell :colspan="colSpan" class="text-center text-muted-foreground">
                                Tidak ada data pengguna.
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
