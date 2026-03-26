<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
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
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { MoreHorizontal, Pencil, Plus, Trash2, Upload, Users, KeyRound, Copy, Check, Download, FileSpreadsheet, FileText, AlertTriangle } from 'lucide-vue-next';
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

// ── Reset Password ──────────────────────────────────────────────────
const showResetModal = ref(false);
const resetResult = ref<{ name: string; username: string; password: string } | null>(null);
const resetLoading = ref(false);
const resetCopied = ref(false);
const confirmResetUserId = ref<number | null>(null);
const confirmResetUserName = ref('');

function confirmResetPassword(user: User) {
    confirmResetUserId.value = user.id;
    confirmResetUserName.value = user.name;
}

function doResetPassword() {
    if (!confirmResetUserId.value) return;
    resetLoading.value = true;

    fetch(`/admin/users/${confirmResetUserId.value}/reset-password`, {
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

// ── Credential Download Banner ──────────────────────────────────────
const page = usePage();
const flash = computed(() => page.props.flash as Record<string, string | number | null>);
const credentialKey = computed(() => flash.value?.credential_key as string | null);
const credentialCount = computed(() => flash.value?.credential_count as number | null);
const countdown = ref(30 * 60); // 30 minutes
let countdownInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    if (credentialKey.value) {
        countdownInterval = setInterval(() => {
            countdown.value--;
            if (countdown.value <= 0 && countdownInterval) {
                clearInterval(countdownInterval);
            }
        }, 1000);
    }
});

onUnmounted(() => {
    if (countdownInterval) clearInterval(countdownInterval);
});

const countdownDisplay = computed(() => {
    const m = Math.floor(countdown.value / 60);
    const s = countdown.value % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
});
</script>

<template>
    <Head title="Manajemen Pengguna" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <!-- Credential Download Banner -->
            <Alert v-if="credentialKey && countdown > 0" class="border-blue-200 bg-blue-50">
                <Download class="size-4 text-blue-600" />
                <AlertTitle class="text-blue-800">Import Berhasil — Download Kredensial</AlertTitle>
                <AlertDescription class="text-blue-700">
                    <p class="mb-3">{{ credentialCount }} siswa berhasil diimport. Download kredensial sebelum expired.</p>
                    <div class="flex items-center gap-3 flex-wrap">
                        <Button variant="default" size="sm" as-child>
                            <a :href="`/admin/credentials/${credentialKey}/pdf`" target="_blank">
                                <FileText class="mr-1 size-4" />Download PDF
                            </a>
                        </Button>
                        <Button variant="outline" size="sm" as-child>
                            <a :href="`/admin/credentials/${credentialKey}/excel`" target="_blank">
                                <FileSpreadsheet class="mr-1 size-4" />Download Excel
                            </a>
                        </Button>
                        <Badge variant="secondary" class="text-xs">
                            <AlertTriangle class="mr-1 size-3" />
                            Expired dalam {{ countdownDisplay }}
                        </Badge>
                    </div>
                </AlertDescription>
            </Alert>

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
                                        <DropdownMenuItem
                                            v-if="user.role === 'siswa'"
                                            @select.prevent="confirmResetPassword(user)"
                                        >
                                            <KeyRound class="mr-2 size-4" />Reset Password
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

        <!-- Reset Password Confirm Dialog -->
        <ConfirmDialog
            :open="!!confirmResetUserId"
            :description="`Reset password untuk ${confirmResetUserName}? Password baru akan di-generate otomatis.`"
            @confirm="doResetPassword"
            @cancel="confirmResetUserId = null"
        />

        <!-- Reset Password Result Modal -->
        <Dialog v-model:open="showResetModal">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Password Berhasil Direset</DialogTitle>
                    <DialogDescription>Catat password baru berikut. Hanya ditampilkan sekali.</DialogDescription>
                </DialogHeader>
                <div v-if="resetResult" class="space-y-3">
                    <div class="rounded-lg border p-4 space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground">Nama</span>
                            <span class="text-sm font-medium">{{ resetResult.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground">NIS</span>
                            <span class="text-sm font-medium">{{ resetResult.username }}</span>
                        </div>
                        <div class="flex items-center justify-between border-t pt-2">
                            <span class="text-sm text-muted-foreground">Password Baru</span>
                            <span class="text-lg font-bold font-mono select-all">{{ resetResult.password }}</span>
                        </div>
                    </div>
                    <Alert variant="destructive">
                        <AlertDescription>Password ini hanya ditampilkan sekali. Pastikan sudah dicatat.</AlertDescription>
                    </Alert>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="copyResetPassword">
                        <component :is="resetCopied ? Check : Copy" class="mr-2 size-4" />
                        {{ resetCopied ? 'Tersalin!' : 'Salin Password' }}
                    </Button>
                    <Button @click="showResetModal = false">Tutup</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
