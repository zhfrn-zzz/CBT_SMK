<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import type { BreadcrumbItem, PaginatedData } from '@/types';
import type { AuditFilters, AuditLogEntry } from '@/types/audit';

const props = defineProps<{
    auditLogs: PaginatedData<AuditLogEntry>;
    users: { id: number; name: string }[];
    filters: AuditFilters;
    actionTypes: string[];
    auditableTypes: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Log Audit', href: '/admin/audit-log' },
];

const userFilter = ref(String(props.filters.user_id ?? 'all'));
const actionFilter = ref(props.filters.action ?? 'all');
const auditableTypeFilter = ref(props.filters.auditable_type ?? 'all');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

const selectedLog = ref<AuditLogEntry | null>(null);
const detailOpen = ref(false);

function applyFilters() {
    router.get(
        '/admin/audit-log',
        {
            user_id: userFilter.value === 'all' ? undefined : userFilter.value,
            action: actionFilter.value === 'all' ? undefined : actionFilter.value,
            auditable_type: auditableTypeFilter.value === 'all' ? undefined : auditableTypeFilter.value,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

watch([userFilter, actionFilter, auditableTypeFilter], applyFilters);

let dateTimeout: ReturnType<typeof setTimeout>;
watch([dateFrom, dateTo], () => {
    clearTimeout(dateTimeout);
    dateTimeout = setTimeout(applyFilters, 400);
});

function openDetail(log: AuditLogEntry) {
    selectedLog.value = log;
    detailOpen.value = true;
}

const actionBadgeVariant: Record<string, string> = {
    created: 'bg-green-100 text-green-800',
    updated: 'bg-blue-100 text-blue-800',
    deleted: 'bg-red-100 text-red-800',
    login: 'bg-purple-100 text-purple-800',
    export: 'bg-orange-100 text-orange-800',
    import: 'bg-teal-100 text-teal-800',
};

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Log Audit" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Log Audit</h2>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <Select v-model="userFilter">
                    <SelectTrigger class="w-[180px]">
                        <SelectValue placeholder="Semua Pengguna" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua Pengguna</SelectItem>
                        <SelectItem v-for="user in users" :key="user.id" :value="String(user.id)">
                            {{ user.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="actionFilter">
                    <SelectTrigger class="w-[160px]">
                        <SelectValue placeholder="Semua Aksi" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua Aksi</SelectItem>
                        <SelectItem v-for="action in actionTypes" :key="action" :value="action">
                            {{ action }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="auditableTypeFilter">
                    <SelectTrigger class="w-[180px]">
                        <SelectValue placeholder="Semua Model" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua Model</SelectItem>
                        <SelectItem v-for="(label, type) in auditableTypes" :key="type" :value="type">
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Input v-model="dateFrom" type="date" class="w-[160px]" />
                <Input v-model="dateTo" type="date" class="w-[160px]" />
            </div>

            <!-- Table -->
            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Waktu</TableHead>
                            <TableHead>Pengguna</TableHead>
                            <TableHead>Aksi</TableHead>
                            <TableHead>Model</TableHead>
                            <TableHead>ID</TableHead>
                            <TableHead>Deskripsi</TableHead>
                            <TableHead>IP</TableHead>
                            <TableHead class="w-[80px]"></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="auditLogs.data.length === 0">
                            <TableCell colspan="8" class="text-center text-muted-foreground py-8">
                                Tidak ada log audit.
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="log in auditLogs.data" :key="log.id">
                            <TableCell class="whitespace-nowrap text-sm">{{ formatDate(log.created_at) }}</TableCell>
                            <TableCell class="text-sm">{{ log.user?.name ?? '—' }}</TableCell>
                            <TableCell>
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="actionBadgeVariant[log.action] ?? 'bg-gray-100 text-gray-800'"
                                >
                                    {{ log.action }}
                                </span>
                            </TableCell>
                            <TableCell class="text-sm">{{ log.auditable_type_label ?? '—' }}</TableCell>
                            <TableCell class="text-sm">{{ log.auditable_id ?? '—' }}</TableCell>
                            <TableCell class="text-sm max-w-[200px] truncate">{{ log.description ?? '—' }}</TableCell>
                            <TableCell class="text-sm font-mono">{{ log.ip_address ?? '—' }}</TableCell>
                            <TableCell>
                                <Button variant="ghost" size="sm" @click="openDetail(log)">
                                    Detail
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Pagination -->
            <Pagination
                :links="auditLogs.links"
                :from="auditLogs.from"
                :to="auditLogs.to"
                :total="auditLogs.total"
            />
        </div>

        <!-- Detail Dialog -->
        <Dialog v-model:open="detailOpen">
            <DialogContent class="max-w-2xl max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Detail Log Audit #{{ selectedLog?.id }}</DialogTitle>
                </DialogHeader>
                <div v-if="selectedLog" class="space-y-4 text-sm">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="font-medium text-muted-foreground">Waktu</p>
                            <p>{{ formatDate(selectedLog.created_at) }}</p>
                        </div>
                        <div>
                            <p class="font-medium text-muted-foreground">Pengguna</p>
                            <p>{{ selectedLog.user?.name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="font-medium text-muted-foreground">Aksi</p>
                            <p>{{ selectedLog.action }}</p>
                        </div>
                        <div>
                            <p class="font-medium text-muted-foreground">Model</p>
                            <p>{{ selectedLog.auditable_type_label ?? '—' }} {{ selectedLog.auditable_id ? `#${selectedLog.auditable_id}` : '' }}</p>
                        </div>
                        <div>
                            <p class="font-medium text-muted-foreground">IP Address</p>
                            <p class="font-mono">{{ selectedLog.ip_address ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="font-medium text-muted-foreground">Deskripsi</p>
                            <p>{{ selectedLog.description ?? '—' }}</p>
                        </div>
                    </div>

                    <div v-if="selectedLog.old_values || selectedLog.new_values" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div v-if="selectedLog.old_values">
                            <p class="font-semibold mb-2">Nilai Lama</p>
                            <table class="w-full text-xs border rounded">
                                <tr v-for="(val, key) in selectedLog.old_values" :key="String(key)" class="border-b last:border-0">
                                    <td class="py-1 px-2 font-medium bg-muted/50 w-1/3">{{ key }}</td>
                                    <td class="py-1 px-2 break-all">{{ val !== null ? String(val) : '—' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div v-if="selectedLog.new_values">
                            <p class="font-semibold mb-2">Nilai Baru</p>
                            <table class="w-full text-xs border rounded">
                                <tr v-for="(val, key) in selectedLog.new_values" :key="String(key)" class="border-b last:border-0">
                                    <td class="py-1 px-2 font-medium bg-muted/50 w-1/3">{{ key }}</td>
                                    <td class="py-1 px-2 break-all">{{ val !== null ? String(val) : '—' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div v-else>
                        <p class="text-muted-foreground">Tidak ada perubahan nilai.</p>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
