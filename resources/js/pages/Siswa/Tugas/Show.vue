<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Download } from 'lucide-vue-next';
import type { Assignment, AssignmentSubmission, BreadcrumbItem } from '@/types';

const props = defineProps<{
    assignment: Assignment;
    submission: AssignmentSubmission | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tugas', href: '/siswa/tugas' },
    { title: props.assignment.title, href: `/siswa/tugas/${props.assignment.id}` },
];

const isOverdue = computed(() => new Date(props.assignment.deadline_at).getTime() < Date.now());
const canSubmit = computed(() => {
    if (props.submission?.graded_at) return false;
    if (isOverdue.value && !props.assignment.allow_late_submission) return false;
    return true;
});

const form = useForm({
    content: props.submission?.content ?? '',
    file: null as File | null,
});

function handleFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    form.file = target.files?.[0] ?? null;
}

function submit() {
    form.post(`/siswa/tugas/${props.assignment.id}/submit`, { forceFormData: true });
}

function formatDate(d: string) {
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function countdown(deadline: string) {
    const diff = new Date(deadline).getTime() - Date.now();
    if (diff <= 0) return null;
    const days = Math.floor(diff / 86400000);
    const hours = Math.floor((diff % 86400000) / 3600000);
    const mins = Math.floor((diff % 3600000) / 60000);
    if (days > 0) return `Sisa ${days} hari ${hours} jam`;
    return `Sisa ${hours} jam ${mins} menit`;
}

const finalScore = computed(() => {
    if (!props.submission?.score) return null;
    if (props.submission.is_late && props.assignment.late_penalty_percent > 0) {
        return props.submission.score * (1 - props.assignment.late_penalty_percent / 100);
    }
    return props.submission.score;
});
</script>

<template>
    <Head :title="assignment.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl p-4 space-y-4">
            <FlashMessage />

            <div>
                <h2 class="text-xl font-semibold">{{ assignment.title }}</h2>
                <p class="text-sm text-muted-foreground">
                    {{ assignment.subject?.name }} · {{ assignment.classroom?.name }}
                </p>
            </div>

            <!-- Deadline info -->
            <div class="rounded-lg border p-3 text-sm space-y-1">
                <p>Deadline: <strong :class="isOverdue ? 'text-red-600' : ''">{{ formatDate(assignment.deadline_at) }}</strong></p>
                <p v-if="!isOverdue && countdown(assignment.deadline_at)" class="text-orange-600">{{ countdown(assignment.deadline_at) }}</p>
                <p v-if="isOverdue && assignment.allow_late_submission" class="text-orange-600">
                    Deadline sudah lewat. Pengumpulan terlambat diizinkan (potongan {{ assignment.late_penalty_percent }}%).
                </p>
                <p v-if="isOverdue && !assignment.allow_late_submission" class="text-red-600">Deadline sudah lewat. Tidak bisa mengumpulkan.</p>
                <p>Nilai maksimal: {{ assignment.max_score }}</p>
                <p>Tipe submission: {{ { file: 'File', text: 'Teks', file_or_text: 'File atau Teks' }[assignment.submission_type] }}</p>
            </div>

            <!-- Description -->
            <div class="prose prose-sm max-w-none rounded-lg border p-4">
                <!-- eslint-disable-next-line vue/no-v-html -->
                <div v-html="assignment.description" />
            </div>

            <!-- Attachment -->
            <Button v-if="assignment.file_path" variant="outline" size="sm" as-child>
                <a :href="`/siswa/tugas/${assignment.id}/download`">
                    <Download class="size-4" />Unduh Lampiran
                </a>
            </Button>

            <!-- If already graded -->
            <div v-if="submission?.graded_at" class="rounded-lg border p-4 space-y-3">
                <div class="flex items-center gap-2">
                    <Badge variant="default">Sudah Dinilai</Badge>
                    <Badge v-if="submission.is_late" variant="destructive">Terlambat</Badge>
                </div>
                <div v-if="submission.content">
                    <p class="font-medium text-sm">Jawaban Anda:</p>
                    <p class="text-sm whitespace-pre-wrap text-muted-foreground">{{ submission.content }}</p>
                </div>
                <div v-if="submission.file_original_name" class="text-sm text-muted-foreground">
                    File: {{ submission.file_original_name }}
                </div>
                <div class="rounded-md bg-muted/50 p-3 space-y-1">
                    <p class="font-medium">Nilai: <span class="text-2xl text-green-600">{{ submission.score }}</span>
                        <span v-if="submission.is_late && assignment.late_penalty_percent > 0" class="text-sm text-muted-foreground">
                            → Final: {{ finalScore?.toFixed(1) }} (potong {{ assignment.late_penalty_percent }}%)
                        </span>
                    </p>
                    <p v-if="submission.feedback" class="text-sm">Feedback: {{ submission.feedback }}</p>
                </div>
            </div>

            <!-- If submitted but not graded -->
            <div v-else-if="submission && !submission.graded_at" class="space-y-3">
                <div class="rounded-lg border p-4 space-y-2">
                    <div class="flex items-center gap-2">
                        <Badge variant="secondary">Sudah Dikumpulkan</Badge>
                        <Badge v-if="submission.is_late" variant="destructive">Terlambat</Badge>
                        <span class="text-xs text-muted-foreground">{{ formatDate(submission.submitted_at) }}</span>
                    </div>
                    <p v-if="submission.content" class="text-sm whitespace-pre-wrap text-muted-foreground">{{ submission.content }}</p>
                    <p v-if="submission.file_original_name" class="text-sm text-muted-foreground">File: {{ submission.file_original_name }}</p>
                </div>

                <div v-if="canSubmit" class="space-y-2">
                    <p class="text-sm font-medium">Edit Jawaban:</p>
                    <form @submit.prevent="submit" class="space-y-3">
                        <div v-if="['text', 'file_or_text'].includes(assignment.submission_type)" class="space-y-1">
                            <Label>Jawaban Teks</Label>
                            <Textarea v-model="form.content" rows="5" />
                        </div>
                        <div v-if="['file', 'file_or_text'].includes(assignment.submission_type)" class="space-y-1">
                            <Label>Ganti File</Label>
                            <Input type="file" @change="handleFileChange" />
                        </div>
                        <Button type="submit" :disabled="form.processing">Update Jawaban</Button>
                    </form>
                </div>
            </div>

            <!-- If not submitted -->
            <div v-else-if="canSubmit" class="space-y-3">
                <p class="font-medium">Kumpulkan Tugas:</p>
                <form @submit.prevent="submit" class="space-y-3">
                    <div v-if="['text', 'file_or_text'].includes(assignment.submission_type)" class="space-y-1">
                        <Label>Jawaban Teks</Label>
                        <Textarea v-model="form.content" rows="5" />
                    </div>
                    <div v-if="['file', 'file_or_text'].includes(assignment.submission_type)" class="space-y-1">
                        <Label>Upload File</Label>
                        <Input type="file" @change="handleFileChange" />
                        <p class="text-xs text-muted-foreground">Maks 25 MB</p>
                    </div>
                    <p v-if="form.errors.submit" class="text-xs text-destructive">{{ form.errors.submit }}</p>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Mengumpulkan...' : 'Kumpulkan Tugas' }}
                    </Button>
                </form>
            </div>

            <div v-else class="rounded-lg border-2 border-dashed p-4 text-center text-muted-foreground text-sm">
                Tidak bisa mengumpulkan tugas (deadline sudah lewat).
            </div>
        </div>
    </AppLayout>
</template>
