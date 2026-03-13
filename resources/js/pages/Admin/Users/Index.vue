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
import { Pencil, Plus, Trash2, Upload } from 'lucide-vue-next';
import type { BreadcrumbItem, PaginatedData, User } from '@/types';

const props = defineProps<{
    users: PaginatedData<User>;
    filters: { search?: string; role?: string };
    roles: { value: string; label: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Manajemen Pengguna', href: '/admin/users' },
];

const search = ref(props.filters.search ?? '');
const roleFilter = ref(props.filters.role ?? 'all');

let searchTimeout: ReturnType<typeof setTimeout>;

watch(search, (val) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            '/admin/users',
            { search: val || undefined, role: roleFilter.value === 'all' ? undefined : roleFilter.value },
            { preserveState: true, replace: true },
        );
    }, 300);
});

watch(roleFilter, (val) => {
    router.get(
        '/admin/users',
        { search: search.value || undefined, role: val === 'all' ? undefined : val },
        { preserveState: true, replace: true },
    );
});

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

            <div class="flex gap-3">
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
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nama</TableHead>
                            <TableHead>Username</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Role</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="w-[100px]">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="user in users.data" :key="user.id">
                            <TableCell class="font-medium">{{ user.name }}</TableCell>
                            <TableCell>{{ user.username }}</TableCell>
                            <TableCell>{{ user.email ?? '-' }}</TableCell>
                            <TableCell>
                                <Badge :variant="roleBadgeVariant(user.role)">
                                    {{ user.role }}
                                </Badge>
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
                            <TableCell :colspan="6" class="text-center text-muted-foreground">
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
