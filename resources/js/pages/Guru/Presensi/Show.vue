<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { ClipboardCheck, RefreshCw } from 'lucide-vue-next';
import type { Attendance, AttendanceRecord, BreadcrumbItem } from '@/types';

interface Student {
    id: number;
    name: string;
    username: string;
}

interface AttendanceStatusCase {
    value: string;
    name: string;
}

const props = defineProps<{
    attendance: Attendance;
    students: Student[];
    records: Record<number, AttendanceRecord>;
    statuses: AttendanceStatusCase[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Presensi', href: '/guru/presensi' },
    { title: `Pertemuan ${props.attendance.meeting_number}`, href: `/guru/presensi/${props.attendance.id}` },
];

const localRecords = ref<Record<number, string>>({});
props.students.forEach((s) => {
    localRecords.value[s.id] = props.records[s.id]?.status?.valueOf() ?? '';
});

const regenerateForm = useForm({ duration_minutes: 30 });

function regenerateCode() {
    regenerateForm.post(`/guru/presensi/${props.attendance.id}/regenerate-code`, { preserveScroll: true });
}

function saveStatus(studentId: number) {
    const status = localRecords.value[studentId];
    if (!status || status === '__none__') return;
    router.put(`/guru/presensi/${props.attendance.id}/status`, {
        records: [{ user_id: studentId, status }],
    }, { preserveScroll: true });
}

function saveAllStatuses() {
    const records = props.students
        .map((s) => ({ user_id: s.id, status: localRecords.value[s.id] || 'alfa' }));
    router.put(`/guru/presensi/${props.attendance.id}/status`, { records }, { preserveScroll: true });
}

function closeSession() {
    router.post(`/guru/presensi/${props.attendance.id}/close`, {}, { preserveScroll: true });
}

const presentCount = computed(() => Object.values(props.records).filter((r) => r.status?.valueOf() === 'hadir').length);
const totalStudents = computed(() => props.students.length);

const statusColor = (status: string) => {
    const map: Record<string, string> = { hadir: 'default', izin: 'secondary', sakit: 'outline', alfa: 'destructive' };
    return map[status] ?? 'outline';
};
</script>

<template>
    <Head :title="`Presensi Pertemuan ${attendance.meeting_number}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <PageHeader title="Sesi Presensi" :description="`${attendance.subject?.name} · ${attendance.classroom?.name} · ${new Date(attendance.meeting_date).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}`" :icon="ClipboardCheck">
                <template #actions>
                    <ConfirmDialog
                        v-if="attendance.is_open"
                        title="Tutup Sesi Presensi"
                        description="Siswa yang belum presensi akan otomatis ditandai Alfa. Lanjutkan?"
                        confirm-label="Tutup"
                        variant="destructive"
                        @confirm="closeSession"
                    >
                        <Button variant="destructive" size="sm">Tutup Sesi</Button>
                    </ConfirmDialog>
                </template>
            </PageHeader>

            <!-- Kode Akses -->
            <div v-if="attendance.is_open" class="rounded-lg border bg-muted/30 p-4 space-y-3">
                <div class="flex items-center gap-4">
                    <div>
                        <p class="text-sm text-muted-foreground">Kode Presensi</p>
                        <p class="text-5xl font-bold tracking-widest font-mono text-primary">{{ attendance.access_code }}</p>
                        <p v-if="attendance.code_expires_at" class="text-xs text-muted-foreground mt-1">
                            Berlaku hingga {{ new Date(attendance.code_expires_at).toLocaleTimeString('id-ID') }}
                        </p>
                        <p v-else class="text-xs text-muted-foreground mt-1">Berlaku sampai sesi ditutup</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Select v-model.number="(regenerateForm.duration_minutes as any)" class="w-40">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="(15 as any)">15 menit</SelectItem>
                            <SelectItem :value="(30 as any)">30 menit</SelectItem>
                            <SelectItem :value="(60 as any)">60 menit</SelectItem>
                            <SelectItem :value="(0 as any)">Manual</SelectItem>
                        </SelectContent>
                    </Select>
                    <LoadingButton variant="outline" size="sm" :loading="regenerateForm.processing" @click="regenerateCode">
                        <RefreshCw class="size-4" />
                        Generate Kode Baru
                    </LoadingButton>
                </div>
            </div>

            <!-- Summary -->
            <div class="flex gap-4 text-sm">
                <span>Hadir: <strong>{{ presentCount }}</strong></span>
                <span>Total: <strong>{{ totalStudents }}</strong></span>
            </div>

            <!-- Students table -->
            <div class="overflow-hidden rounded-lg border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Nama</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Username</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Status</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Check-in</TableHead>
                            <TableHead v-if="attendance.is_open" class="text-xs font-semibold uppercase tracking-wider">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="s in students" :key="s.id" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
                            <TableCell class="font-medium">{{ s.name }}</TableCell>
                            <TableCell class="text-muted-foreground">{{ s.username }}</TableCell>
                            <TableCell>
                                <Select v-if="attendance.is_open" v-model="localRecords[s.id]" @update:model-value="saveStatus(s.id)">
                                    <SelectTrigger class="w-28 h-7">
                                        <SelectValue placeholder="-" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none__">-</SelectItem>
                                        <SelectItem v-for="st in statuses" :key="st.value" :value="st.value">{{ st.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Badge v-else :variant="statusColor(records[s.id]?.status?.valueOf() ?? '') as any">
                                    {{ records[s.id]?.status?.valueOf() ?? '-' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                {{ records[s.id]?.checked_in_at ? new Date(records[s.id].checked_in_at!).toLocaleTimeString('id-ID') : '-' }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-if="attendance.is_open" class="flex items-center justify-end">
                <LoadingButton size="sm" @click="saveAllStatuses">Simpan Semua</LoadingButton>
            </div>
        </div>
    </AppLayout>
</template>
