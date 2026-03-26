<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatsCard from '@/Components/StatsCard.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import LoadingButton from '@/Components/LoadingButton.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { CheckCircle, ClipboardList, Download, FileText, Pencil, Trash2, Users, XCircle } from 'lucide-vue-next';
import type { Assignment, AssignmentSubmission, BreadcrumbItem } from '@/types';

interface SubmissionStats {
    total_students: number;
    submitted: number;
    not_submitted: number;
    graded: number;
    late: number;
}

const props = defineProps<{
    assignment: Assignment;
    submissions: AssignmentSubmission[];
    students: { id: number; name: string; username: string }[];
    stats: SubmissionStats;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tugas', href: '/guru/tugas' },
    { title: props.assignment.title, href: `/guru/tugas/${props.assignment.id}` },
];

const gradingId = ref<number | null>(null);
const gradeForm = useForm({ score: 0, feedback: '' });

function openGrading(submission: AssignmentSubmission) {
    gradingId.value = submission.id;
    gradeForm.score = submission.score ?? 0;
    gradeForm.feedback = submission.feedback ?? '';
}

function submitGrade(submissionId: number) {
    gradeForm.put(`/guru/tugas/submissions/${submissionId}/grade`, {
        onSuccess: () => { gradingId.value = null; },
    });
}

function deleteAssignment() {
    router.delete(`/guru/tugas/${props.assignment.id}`);
}

const statusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    const map: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        not_submitted: 'outline',
        submitted: 'secondary',
        late: 'destructive',
        graded: 'default',
    };
    return map[status] ?? 'outline';
};

const statusLabel = (status: string) => {
    const map: Record<string, string> = {
        not_submitted: 'Belum Submit',
        submitted: 'Sudah Submit',
        late: 'Terlambat',
        graded: 'Sudah Dinilai',
    };
    return map[status] ?? status;
};

function formatDate(d: string | null) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function getFinalScore(submission: AssignmentSubmission) {
    if (!submission.score) return '-';
    if (submission.is_late && props.assignment.late_penalty_percent > 0) {
        const final = submission.score * (1 - props.assignment.late_penalty_percent / 100);
        return `${submission.score} → ${final.toFixed(1)} (potong ${props.assignment.late_penalty_percent}%)`;
    }
    return submission.score;
}
</script>

<template>
    <Head :title="assignment.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <PageHeader :title="assignment.title" :description="`${assignment.subject?.name} · ${assignment.classroom?.name} · Deadline: ${formatDate(assignment.deadline_at)}`" :icon="FileText">
                <template #actions>
                    <Button variant="outline" size="sm" as-child v-if="assignment.file_path">
                        <a :href="`/guru/tugas/${assignment.id}/download`"><Download class="size-4" /><span class="hidden sm:inline">Lampiran</span></a>
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/guru/tugas/${assignment.id}/edit`"><Pencil class="size-4" /><span class="hidden sm:inline">Edit</span></Link>
                    </Button>
                    <ConfirmDialog
                        title="Hapus Tugas?"
                        description="Semua submission akan ikut terhapus permanen. Lanjutkan?"
                        confirm-label="Ya, Hapus"
                        @confirm="deleteAssignment"
                    >
                        <Button variant="destructive" size="sm">
                            <Trash2 class="size-4" />
                            <span class="hidden sm:inline">Hapus</span>
                        </Button>
                    </ConfirmDialog>
                </template>
            </PageHeader>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatsCard title="Total Siswa" :value="stats.total_students" :icon="Users" />
                <StatsCard title="Sudah Submit" :value="stats.submitted" :icon="ClipboardList" icon-color="bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400" />
                <StatsCard title="Belum Submit" :value="stats.not_submitted" :icon="XCircle" icon-color="bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400" />
                <StatsCard title="Sudah Dinilai" :value="stats.graded" :icon="CheckCircle" icon-color="bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400" />
            </div>

            <!-- Submissions table -->
            <div class="overflow-x-auto rounded-lg border bg-card">
                <Table class="min-w-[650px]">
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Siswa</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Status</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Waktu Submit</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Nilai</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-for="student in students" :key="student.id">
                            <TableRow class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
                                <TableCell>
                                    <p class="font-medium">{{ student.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ student.username }}</p>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="statusVariant(submissions.find(s => s.user_id === student.id)?.status ?? 'not_submitted')">
                                        {{ statusLabel(submissions.find(s => s.user_id === student.id)?.status ?? 'not_submitted') }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-sm">
                                    {{ formatDate(submissions.find(s => s.user_id === student.id)?.submitted_at ?? null) }}
                                </TableCell>
                                <TableCell class="text-sm">
                                    {{ getFinalScore(submissions.find(s => s.user_id === student.id) as AssignmentSubmission) }}
                                </TableCell>
                                <TableCell>
                                    <div v-if="submissions.find(s => s.user_id === student.id)" class="flex gap-1">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="openGrading(submissions.find(s => s.user_id === student.id)!)"
                                        >
                                            Nilai
                                        </Button>
                                        <Button
                                            v-if="submissions.find(s => s.user_id === student.id)?.file_path"
                                            variant="ghost"
                                            size="sm"
                                            as-child
                                        >
                                            <a :href="`/guru/tugas/submissions/${submissions.find(s => s.user_id === student.id)?.id}/download`">
                                                <Download class="size-4" />
                                            </a>
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>

                            <!-- Inline grading form -->
                            <TableRow v-if="gradingId === submissions.find(s => s.user_id === student.id)?.id">
                                <TableCell colspan="5">
                                    <div class="space-y-3 rounded-md bg-muted/50 p-3">
                                        <div v-if="submissions.find(s => s.user_id === student.id)?.content" class="text-sm">
                                            <p class="font-medium mb-1">Jawaban Teks:</p>
                                            <p class="text-muted-foreground whitespace-pre-wrap">{{ submissions.find(s => s.user_id === student.id)?.content }}</p>
                                        </div>
                                        <div class="flex flex-col sm:flex-row gap-3 sm:items-end">
                                            <div class="space-y-1">
                                                <Label class="font-semibold text-sm">Nilai (0 - {{ assignment.max_score }})</Label>
                                                <Input v-model.number="gradeForm.score" type="number" :min="0" :max="assignment.max_score" class="w-24 h-11" />
                                            </div>
                                            <div class="flex-1 space-y-1">
                                                <Label class="font-semibold text-sm">Feedback</Label>
                                                <Textarea v-model="gradeForm.feedback" rows="2" />
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <LoadingButton :loading="gradeForm.processing" size="sm" @click="submitGrade(submissions.find(s => s.user_id === student.id)!.id)">
                                                Simpan Nilai
                                            </LoadingButton>
                                            <Button size="sm" variant="ghost" @click="gradingId = null">Batal</Button>
                                        </div>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </template>
                    </TableBody>
                </Table>
            </div>
        </div>
    </AppLayout>
</template>
