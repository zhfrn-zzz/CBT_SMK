<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import type { Assignment, AssignmentSubmission, BreadcrumbItem } from '@/types';

interface AssignmentWithSubmission extends Assignment {
    my_submission: AssignmentSubmission | null;
}

const props = defineProps<{
    assignments: AssignmentWithSubmission[];
    filters: { subject_id: number | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/siswa/dashboard' },
    { title: 'Tugas', href: '/siswa/tugas' },
];

function statusBadge(a: AssignmentWithSubmission): { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' } {
    if (!a.my_submission) {
        if (a.is_overdue) return { label: 'Deadline Lewat', variant: 'destructive' };
        return { label: 'Belum Submit', variant: 'outline' };
    }
    if (a.my_submission.graded_at) return { label: 'Sudah Dinilai', variant: 'default' };
    if (a.my_submission.is_late) return { label: 'Terlambat', variant: 'destructive' };
    return { label: 'Sudah Submit', variant: 'secondary' };
}

function formatDate(d: string) {
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function countdown(deadline: string) {
    const diff = new Date(deadline).getTime() - Date.now();
    if (diff <= 0) return 'Deadline sudah lewat';
    const days = Math.floor(diff / 86400000);
    const hours = Math.floor((diff % 86400000) / 3600000);
    if (days > 0) return `${days} hari ${hours} jam lagi`;
    return `${hours} jam lagi`;
}
</script>

<template>
    <Head title="Tugas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />
            <h2 class="text-xl font-semibold">Tugas & Assignment</h2>

            <div v-if="assignments.length === 0" class="rounded-lg border-2 border-dashed p-10 text-center text-muted-foreground">
                Tidak ada tugas saat ini.
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="a in assignments" :key="a.id"
                    :href="`/siswa/tugas/${a.id}`"
                    class="block rounded-lg border p-4 hover:bg-muted/50 transition-colors space-y-2"
                    :class="a.is_overdue && !a.my_submission ? 'border-red-300 bg-red-50/50 dark:bg-red-950/10' : ''"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-medium line-clamp-2">{{ a.title }}</p>
                        <Badge :variant="statusBadge(a).variant" class="shrink-0 text-xs">
                            {{ statusBadge(a).label }}
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground">{{ a.subject?.name }} · {{ a.classroom?.name }}</p>
                    <div class="text-xs">
                        <p class="text-muted-foreground">Deadline: {{ formatDate(a.deadline_at) }}</p>
                        <p v-if="!a.is_overdue" class="text-orange-600">{{ countdown(a.deadline_at) }}</p>
                    </div>
                    <div v-if="a.my_submission?.score !== null && a.my_submission?.score !== undefined" class="text-sm font-medium text-green-600">
                        Nilai: {{ a.my_submission.score }}
                        <span v-if="a.my_submission.is_late && a.late_penalty_percent > 0" class="text-xs text-muted-foreground">
                            → {{ (a.my_submission.score * (1 - a.late_penalty_percent / 100)).toFixed(1) }} (potong {{ a.late_penalty_percent }}%)
                        </span>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
