<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { Download, Pencil } from 'lucide-vue-next';
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
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />

            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold">{{ assignment.title }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ assignment.subject?.name }} · {{ assignment.classroom?.name }} · Deadline: {{ formatDate(assignment.deadline_at) }}
                    </p>
                </div>
                <div class="flex gap-2 shrink-0">
                    <Button variant="outline" size="sm" as-child v-if="assignment.file_path">
                        <a :href="`/guru/tugas/${assignment.id}/download`"><Download class="size-4" />Lampiran</a>
                    </Button>
                    <Button size="sm" as-child>
                        <Link :href="`/guru/tugas/${assignment.id}/edit`"><Pencil class="size-4" />Edit</Link>
                    </Button>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <Card v-for="[label, value, extra] in [
                    ['Total Siswa', stats.total_students, ''],
                    ['Sudah Submit', stats.submitted, ''],
                    ['Belum Submit', stats.not_submitted, ''],
                    ['Sudah Dinilai', stats.graded, ''],
                ]" :key="label">
                    <CardContent class="pt-4 pb-3">
                        <p class="text-2xl font-bold">{{ value }}</p>
                        <p class="text-xs text-muted-foreground">{{ label }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Submissions table -->
            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Siswa</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Waktu Submit</TableHead>
                            <TableHead>Nilai</TableHead>
                            <TableHead>Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-for="student in students" :key="student.id">
                            <TableRow>
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
                                        <div class="flex gap-3 items-end">
                                            <div class="space-y-1">
                                                <Label>Nilai (0 - {{ assignment.max_score }})</Label>
                                                <Input v-model.number="gradeForm.score" type="number" :min="0" :max="assignment.max_score" class="w-24" />
                                            </div>
                                            <div class="flex-1 space-y-1">
                                                <Label>Feedback</Label>
                                                <Textarea v-model="gradeForm.feedback" rows="2" />
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <Button size="sm" :disabled="gradeForm.processing" @click="submitGrade(submissions.find(s => s.user_id === student.id)!.id)">
                                                Simpan Nilai
                                            </Button>
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
