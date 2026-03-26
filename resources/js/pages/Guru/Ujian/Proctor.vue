<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatsCard from '@/components/StatsCard.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Progress } from '@/components/ui/progress';
import {
    AlertTriangle,
    ArrowLeft,
    Clock,
    Copy,
    MonitorCheck,
    Search,
    TimerOff,
    UserCheck,
    UserX,
    Users,
    XCircle,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';
import { useProctorChannel } from '@/composables/useProctorChannel';
import type {
    BreadcrumbItem,
    ProctorExamSession,
    ProctorStudent,
    ProctorSummary,
} from '@/types';

const props = defineProps<{
    exam_session: ProctorExamSession;
    students: ProctorStudent[];
    summary: ProctorSummary;
    questions: { id: number; order: number; content: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Ujian', href: '/guru/ujian' },
    { title: props.exam_session.name, href: `/guru/ujian/${props.exam_session.id}` },
    { title: 'Proctor', href: `/guru/ujian/${props.exam_session.id}/proctor` },
];

// Reactive student list for real-time updates
const studentList = ref<ProctorStudent[]>([...props.students]);
const searchQuery = ref('');
const statusFilter = ref<string>('all');

// Connect WebSocket
useProctorChannel(props.exam_session.id, studentList);

// Update remaining_seconds every second for in-progress students
const timerInterval = setInterval(() => {
    studentList.value.forEach((s) => {
        if (s.status === 'in_progress' && s.remaining_seconds > 0) {
            s.remaining_seconds--;
        }
    });
}, 1000);

onUnmounted(() => clearInterval(timerInterval));

// Computed summary from reactive data
const liveSummary = computed(() => ({
    total: studentList.value.length,
    not_started: studentList.value.filter((s) => s.status === 'not_started').length,
    in_progress: studentList.value.filter((s) => s.status === 'in_progress').length,
    submitted: studentList.value.filter((s) => s.status === 'submitted').length,
    graded: studentList.value.filter((s) => s.status === 'graded').length,
}));

const filteredStudents = computed(() => {
    let result = studentList.value;

    if (statusFilter.value !== 'all') {
        result = result.filter((s) => s.status === statusFilter.value);
    }

    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(
            (s) => s.name.toLowerCase().includes(q) || s.username.toLowerCase().includes(q),
        );
    }

    return result;
});

function formatTime(seconds: number): string {
    if (seconds <= 0) return '00:00';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    const map: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        not_started: 'outline',
        in_progress: 'default',
        submitted: 'secondary',
        graded: 'secondary',
    };
    return map[status] ?? 'outline';
}

function copyToken() {
    navigator.clipboard.writeText(props.exam_session.token);
}

// Override dialogs
const showExtendDialog = ref(false);
const showTerminateDialog = ref(false);
const selectedStudent = ref<ProctorStudent | null>(null);

const extendForm = useForm({
    attempt_id: 0,
    additional_minutes: 15,
});

const terminateForm = useForm({
    attempt_id: 0,
    reason: '',
});

function openExtendDialog(student: ProctorStudent) {
    selectedStudent.value = student;
    extendForm.attempt_id = student.attempt_id!;
    extendForm.additional_minutes = 15;
    showExtendDialog.value = true;
}

function submitExtendTime() {
    extendForm.post(`/guru/ujian/${props.exam_session.id}/proctor/extend-time`, {
        preserveScroll: true,
        onSuccess: () => {
            showExtendDialog.value = false;
            if (selectedStudent.value) {
                selectedStudent.value.remaining_seconds += extendForm.additional_minutes * 60;
            }
        },
    });
}

function openTerminateDialog(student: ProctorStudent) {
    selectedStudent.value = student;
    terminateForm.attempt_id = student.attempt_id!;
    terminateForm.reason = '';
    showTerminateDialog.value = true;
}

function submitTerminate() {
    terminateForm.post(`/guru/ujian/${props.exam_session.id}/proctor/terminate`, {
        preserveScroll: true,
        onSuccess: () => {
            showTerminateDialog.value = false;
        },
    });
}

const showInvalidateDialog = ref(false);
const invalidateForm = useForm({
    question_id: 0,
});

function openInvalidateDialog() {
    invalidateForm.question_id = 0;
    showInvalidateDialog.value = true;
}

function submitInvalidateQuestion() {
    if (!invalidateForm.question_id) return;

    invalidateForm.post(`/guru/ujian/${props.exam_session.id}/proctor/invalidate-question`, {
        preserveScroll: true,
        onSuccess: () => {
            showInvalidateDialog.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Proctor - ${exam_session.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <PageHeader :title="'Proctor - ' + exam_session.name" :description="exam_session.subject" :icon="MonitorCheck">
                <template #actions>
                    <Button variant="outline" size="sm" @click="openInvalidateDialog">
                        <XCircle class="mr-1 size-4" />
                        Batalkan Soal
                    </Button>
                    <Button variant="outline" size="sm" @click="copyToken">
                        <Copy class="mr-1 size-4" />
                        <code class="font-mono">{{ exam_session.token }}</code>
                    </Button>
                    <Button variant="ghost" size="sm" as-child>
                        <a :href="`/guru/ujian/${exam_session.id}`">
                            <ArrowLeft class="mr-1 size-4" />
                            Kembali
                        </a>
                    </Button>
                </template>
            </PageHeader>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatsCard title="Total Peserta" :value="liveSummary.total" :icon="Users" />
                <StatsCard title="Sedang Mengerjakan" :value="liveSummary.in_progress" :icon="Clock" iconColor="bg-blue-100 text-blue-600" />
                <StatsCard title="Sudah Selesai" :value="liveSummary.submitted + liveSummary.graded" :icon="UserCheck" iconColor="bg-green-100 text-green-600" />
                <StatsCard title="Belum Mulai" :value="liveSummary.not_started" :icon="UserX" iconColor="bg-gray-100 text-gray-500" />
            </div>

            <!-- Filters -->
            <div class="flex items-center gap-3">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                    <Input v-model="searchQuery" placeholder="Cari siswa..." class="pl-9" />
                </div>
                <div class="flex gap-1">
                    <Button
                        v-for="filter in [
                            { value: 'all', label: 'Semua' },
                            { value: 'not_started', label: 'Belum Mulai' },
                            { value: 'in_progress', label: 'Mengerjakan' },
                            { value: 'submitted', label: 'Selesai' },
                        ]"
                        :key="filter.value"
                        :variant="statusFilter === filter.value ? 'default' : 'outline'"
                        size="sm"
                        @click="statusFilter = filter.value"
                    >
                        {{ filter.label }}
                    </Button>
                </div>
            </div>

            <!-- Student Table -->
            <div class="overflow-hidden rounded-lg border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow class="bg-slate-50 dark:bg-slate-800/50">
                                <TableHead class="w-[40px] text-xs font-semibold uppercase tracking-wider">#</TableHead>
                                <TableHead class="text-xs font-semibold uppercase tracking-wider">Nama</TableHead>
                                <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Status</TableHead>
                                <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Progress</TableHead>
                                <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Sisa Waktu</TableHead>
                                <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Pelanggaran</TableHead>
                                <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="(student, index) in filteredStudents"
                                :key="student.id"
                                :class="{ 'bg-red-50 dark:bg-red-950/20': student.violation_count > 0 && student.status === 'in_progress' }"
                            >
                                <TableCell class="text-muted-foreground">{{ index + 1 }}</TableCell>
                                <TableCell>
                                    <div>
                                        <p class="font-medium">{{ student.name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ student.username }}</p>
                                    </div>
                                </TableCell>
                                <TableCell class="text-center">
                                    <Badge :variant="statusVariant(student.status)">
                                        {{ student.status_label }}
                                    </Badge>
                                    <Badge v-if="student.is_force_submitted" variant="destructive" class="ml-1">
                                        Force
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-center">
                                    <div v-if="student.status !== 'not_started'" class="flex flex-col items-center gap-1">
                                        <span class="text-sm">{{ student.answered_count }}/{{ student.total_questions }}</span>
                                        <Progress
                                            :model-value="student.total_questions > 0 ? (student.answered_count / student.total_questions) * 100 : 0"
                                            class="h-1.5 w-16"
                                        />
                                    </div>
                                    <span v-else class="text-muted-foreground">-</span>
                                </TableCell>
                                <TableCell class="text-center">
                                    <span
                                        v-if="student.status === 'in_progress'"
                                        class="font-mono text-sm"
                                        :class="{
                                            'text-red-600 font-bold': student.remaining_seconds <= 60,
                                            'text-orange-500': student.remaining_seconds > 60 && student.remaining_seconds <= 300,
                                        }"
                                    >
                                        {{ formatTime(student.remaining_seconds) }}
                                    </span>
                                    <span v-else class="text-muted-foreground">-</span>
                                </TableCell>
                                <TableCell class="text-center">
                                    <div v-if="student.violation_count > 0" class="flex items-center justify-center gap-1">
                                        <AlertTriangle class="size-4 text-orange-500" />
                                        <span class="text-sm font-medium text-orange-600">{{ student.violation_count }}</span>
                                    </div>
                                    <span v-else class="text-muted-foreground">-</span>
                                </TableCell>
                                <TableCell class="text-center">
                                    <div v-if="student.status === 'in_progress'" class="flex items-center justify-center gap-1">
                                        <Button variant="outline" size="sm" @click="openExtendDialog(student)">
                                            <Clock class="size-3" />
                                        </Button>
                                        <Button variant="destructive" size="sm" @click="openTerminateDialog(student)">
                                            <TimerOff class="size-3" />
                                        </Button>
                                    </div>
                                    <span v-else-if="student.status === 'submitted' || student.status === 'graded'" class="text-sm">
                                        {{ student.score !== null ? student.score.toFixed(1) : '-' }}
                                    </span>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="filteredStudents.length === 0">
                                <TableCell :colspan="7" class="text-center py-8 text-muted-foreground">
                                    Tidak ada siswa yang cocok dengan filter.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
            </div>
        </div>

        <!-- Extend Time Dialog -->
        <Dialog v-model:open="showExtendDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Tambah Waktu</DialogTitle>
                    <DialogDescription>
                        Tambah waktu ujian untuk {{ selectedStudent?.name }}.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <div class="grid grid-cols-4 items-center gap-4">
                        <label class="text-right text-sm">Menit</label>
                        <Input
                            v-model.number="extendForm.additional_minutes"
                            type="number"
                            min="1"
                            max="120"
                            class="col-span-3"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showExtendDialog = false">Batal</Button>
                    <LoadingButton :loading="extendForm.processing" @click="submitExtendTime">
                        Tambah Waktu
                    </LoadingButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Terminate Dialog -->
        <AlertDialog v-model:open="showTerminateDialog">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Terminasi Ujian</AlertDialogTitle>
                    <AlertDialogDescription>
                        Apakah Anda yakin ingin menghentikan ujian {{ selectedStudent?.name }}?
                        Jawaban yang sudah disimpan akan dikumpulkan secara otomatis.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <div class="py-2">
                    <Input
                        v-model="terminateForm.reason"
                        placeholder="Alasan terminasi (opsional)"
                    />
                </div>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <LoadingButton
                        variant="destructive"
                        :loading="terminateForm.processing"
                        @click="submitTerminate"
                    >
                        Terminasi
                    </LoadingButton>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Invalidate Question Dialog -->
        <Dialog v-model:open="showInvalidateDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Batalkan Soal</DialogTitle>
                    <DialogDescription>
                        Pilih soal yang akan dibatalkan. Semua siswa akan mendapat nilai penuh untuk soal ini.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <div class="max-h-60 overflow-y-auto space-y-2">
                        <label
                            v-for="q in questions"
                            :key="q.id"
                            class="flex items-start gap-3 p-2 rounded-md hover:bg-muted cursor-pointer"
                            :class="{ 'bg-muted': invalidateForm.question_id === q.id }"
                        >
                            <input
                                type="radio"
                                :value="q.id"
                                v-model="invalidateForm.question_id"
                                class="mt-1"
                            />
                            <div class="text-sm">
                                <span class="font-medium">Soal {{ q.order }}:</span>
                                <span class="text-muted-foreground ml-1">{{ q.content.substring(0, 100) + (q.content.length > 100 ? '...' : '') }}</span>
                            </div>
                        </label>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showInvalidateDialog = false">Batal</Button>
                    <LoadingButton variant="destructive" :loading="invalidateForm.processing" :disabled="!invalidateForm.question_id" @click="submitInvalidateQuestion">
                        Batalkan Soal
                    </LoadingButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
