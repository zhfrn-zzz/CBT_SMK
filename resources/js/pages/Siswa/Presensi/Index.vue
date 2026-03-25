<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import Pagination from '@/components/Pagination.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatsCard from '@/Components/StatsCard.vue';
import LoadingButton from '@/Components/LoadingButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { ClipboardCheck, CheckSquare, AlertTriangle, Thermometer, XCircle } from 'lucide-vue-next';
import type { AttendanceRecord, BreadcrumbItem, PaginatedData } from '@/types';

interface AttendanceRecordWithSession extends AttendanceRecord {
    attendance: {
        id: number;
        meeting_date: string;
        meeting_number: number;
        subject: { name: string };
        classroom: { name: string };
    };
}

interface Summary {
    total: number;
    hadir: number;
    izin: number;
    sakit: number;
    alfa: number;
    percentage: number;
}

const props = defineProps<{
    records: PaginatedData<AttendanceRecordWithSession>;
    summary: Record<string, Summary>;
    filters: { subject_id: number | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/siswa/dashboard' },
    { title: 'Presensi', href: '/siswa/presensi' },
];

const form = useForm({ code: '' });

function checkIn() {
    form.post('/siswa/presensi/check-in', { onSuccess: () => { form.reset(); } });
}

const statusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    const map: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        hadir: 'default',
        izin: 'secondary',
        sakit: 'outline',
        alfa: 'destructive',
    };
    return map[status] ?? 'outline';
};

function formatDate(d: string) {
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

const codeDigits = ref(['', '', '', '', '', '']);

function handleDigitInput(index: number, e: Event) {
    const target = e.target as HTMLInputElement;
    const val = target.value.replace(/\D/g, '').slice(-1);
    codeDigits.value[index] = val;
    form.code = codeDigits.value.join('');

    if (val && index < 5) {
        const next = document.getElementById(`code-digit-${index + 1}`);
        next?.focus();
    }
}

function handleKeyDown(index: number, e: KeyboardEvent) {
    if (e.key === 'Backspace' && !codeDigits.value[index] && index > 0) {
        const prev = document.getElementById(`code-digit-${index - 1}`);
        prev?.focus();
    }
}
</script>

<template>
    <Head title="Presensi Saya" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />
            <PageHeader title="Presensi" description="Riwayat kehadiran" :icon="ClipboardCheck" />

            <!-- Check-in form -->
            <Card class="max-w-sm">
                <CardContent class="p-4 space-y-3">
                    <p class="font-medium text-sm">Input Kode Presensi</p>
                    <form @submit.prevent="checkIn" class="space-y-3">
                        <div class="flex gap-2">
                            <Input
                                v-for="i in 6" :key="i"
                                :id="`code-digit-${i - 1}`"
                                v-model="codeDigits[i - 1]"
                                maxlength="1"
                                class="w-10 h-12 text-center text-xl font-bold font-mono p-0"
                                inputmode="numeric"
                                @input="handleDigitInput(i - 1, $event)"
                                @keydown="handleKeyDown(i - 1, $event)"
                            />
                        </div>
                        <p v-if="form.errors.code" class="text-xs text-destructive">{{ form.errors.code }}</p>
                        <LoadingButton type="submit" :loading="form.processing" :disabled="form.code.length < 6">Hadir</LoadingButton>
                    </form>
                </CardContent>
            </Card>

            <!-- Summary cards -->
            <div v-if="Object.keys(summary).length > 0" class="space-y-4">
                <div v-for="(s, subjectKey) in summary" :key="subjectKey" class="space-y-2">
                    <div class="grid gap-3 grid-cols-2 sm:grid-cols-4">
                        <StatsCard title="Hadir" :value="s.hadir" :icon="CheckSquare" icon-color="bg-green-100 text-green-600" />
                        <StatsCard title="Sakit" :value="s.sakit" :icon="Thermometer" icon-color="bg-yellow-100 text-yellow-600" />
                        <StatsCard title="Izin" :value="s.izin" :icon="AlertTriangle" icon-color="bg-blue-100 text-blue-600" />
                        <StatsCard title="Alfa" :value="s.alfa" :icon="XCircle" icon-color="bg-red-100 text-red-600" />
                    </div>
                </div>
            </div>

            <!-- Records table -->
            <EmptyState v-if="records.data.length === 0" :icon="ClipboardCheck" title="Belum ada data presensi" description="Belum ada data presensi saat ini." />
            <div v-else class="overflow-hidden rounded-xl border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Tanggal</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Mata Pelajaran</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Pertemuan</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider text-center">Status</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Check-in</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="r in records.data" :key="r.id" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
                            <TableCell>{{ formatDate(r.attendance.meeting_date) }}</TableCell>
                            <TableCell>{{ r.attendance.subject.name }}</TableCell>
                            <TableCell class="text-center">{{ r.attendance.meeting_number }}</TableCell>
                            <TableCell class="text-center">
                                <StatusBadge :label="r.status?.valueOf() ?? ''" :variant="statusVariant(r.status?.valueOf() ?? '')" />
                            </TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                {{ r.checked_in_at ? new Date(r.checked_in_at).toLocaleTimeString('id-ID') : '-' }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination :links="records.links" :from="records.from" :to="records.to" :total="records.total" />
        </div>
    </AppLayout>
</template>
