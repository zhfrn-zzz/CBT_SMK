<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
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
            <h2 class="text-xl font-semibold">Presensi</h2>

            <!-- Check-in form -->
            <div class="rounded-lg border p-4 space-y-3 max-w-sm">
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
                    <Button type="submit" :disabled="form.processing || form.code.length < 6">
                        {{ form.processing ? 'Memproses...' : 'Hadir' }}
                    </Button>
                </form>
            </div>

            <!-- Summary cards -->
            <div v-if="Object.keys(summary).length > 0" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="(s, subjectId) in summary" :key="subjectId">
                    <CardContent class="pt-3 pb-3">
                        <div class="flex justify-between items-center mb-2">
                            <p class="text-sm font-medium">Rekap</p>
                            <p class="text-lg font-bold" :class="s.percentage < 75 ? 'text-red-600' : 'text-green-600'">
                                {{ s.percentage }}%
                            </p>
                        </div>
                        <div class="flex gap-2 text-xs text-muted-foreground">
                            <span class="text-green-600">H:{{ s.hadir }}</span>
                            <span class="text-blue-600">I:{{ s.izin }}</span>
                            <span class="text-yellow-600">S:{{ s.sakit }}</span>
                            <span class="text-red-600">A:{{ s.alfa }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Records table -->
            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Tanggal</TableHead>
                            <TableHead>Mata Pelajaran</TableHead>
                            <TableHead>Pertemuan</TableHead>
                            <TableHead class="text-center">Status</TableHead>
                            <TableHead>Check-in</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="r in records.data" :key="r.id">
                            <TableCell>{{ formatDate(r.attendance.meeting_date) }}</TableCell>
                            <TableCell>{{ r.attendance.subject.name }}</TableCell>
                            <TableCell class="text-center">{{ r.attendance.meeting_number }}</TableCell>
                            <TableCell class="text-center">
                                <Badge :variant="statusVariant(r.status?.valueOf() ?? '')">
                                    {{ r.status?.valueOf() }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                {{ r.checked_in_at ? new Date(r.checked_in_at).toLocaleTimeString('id-ID') : '-' }}
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="records.data.length === 0">
                            <TableCell :colspan="5" class="text-center text-muted-foreground">Belum ada data presensi.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination :links="records.links" :from="records.from" :to="records.to" :total="records.total" />
        </div>
    </AppLayout>
</template>
