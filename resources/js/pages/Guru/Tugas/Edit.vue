<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import type { Assignment, BreadcrumbItem } from '@/types';

interface TeachingAssignment {
    id: number;
    classroom: { id: number; name: string };
    subject: { id: number; name: string };
}

const props = defineProps<{
    assignment: Assignment;
    teachingAssignments: TeachingAssignment[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tugas', href: '/guru/tugas' },
    { title: props.assignment.title, href: `/guru/tugas/${props.assignment.id}` },
    { title: 'Edit', href: `/guru/tugas/${props.assignment.id}/edit` },
];

const form = useForm({
    title: props.assignment.title,
    description: props.assignment.description,
    subject_id: String(props.assignment.subject_id),
    classroom_id: String(props.assignment.classroom_id),
    file: null as File | null,
    deadline_at: props.assignment.deadline_at.slice(0, 16),
    max_score: props.assignment.max_score,
    allow_late_submission: props.assignment.allow_late_submission,
    late_penalty_percent: props.assignment.late_penalty_percent,
    submission_type: props.assignment.submission_type,
    is_published: props.assignment.is_published,
    _method: 'PUT',
});

const uniqueSubjects = computed(() => {
    const map = new Map<number, { id: number; name: string }>();
    props.teachingAssignments.forEach((a) => { if (!map.has(a.subject.id)) map.set(a.subject.id, a.subject); });
    return Array.from(map.values());
});

const filteredClassrooms = computed(() =>
    props.teachingAssignments.filter((a) => String(a.subject.id) === form.subject_id).map((a) => a.classroom),
);

function handleFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    form.file = target.files?.[0] ?? null;
}

function submit() {
    form.post(`/guru/tugas/${props.assignment.id}`, { forceFormData: true });
}
</script>

<template>
    <Head :title="`Edit: ${assignment.title}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl p-4">
            <h2 class="mb-4 text-xl font-semibold">Edit Tugas</h2>
            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label>Mata Pelajaran</Label>
                        <Select v-model="form.subject_id">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="s in uniqueSubjects" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1">
                        <Label>Kelas</Label>
                        <Select v-model="form.classroom_id">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="c in filteredClassrooms" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="space-y-1">
                    <Label>Judul <span class="text-destructive">*</span></Label>
                    <Input v-model="form.title" />
                    <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>
                </div>

                <div class="space-y-1">
                    <Label>Deskripsi <span class="text-destructive">*</span></Label>
                    <Textarea v-model="form.description" rows="5" />
                </div>

                <div class="space-y-1">
                    <Label>Ganti Lampiran</Label>
                    <p v-if="assignment.file_original_name" class="text-sm text-muted-foreground">File saat ini: {{ assignment.file_original_name }}</p>
                    <Input type="file" @change="handleFileChange" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label>Deadline</Label>
                        <Input v-model="form.deadline_at" type="datetime-local" />
                        <p v-if="form.errors.deadline_at" class="text-xs text-destructive">{{ form.errors.deadline_at }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label>Nilai Maksimal</Label>
                        <Input v-model.number="form.max_score" type="number" min="1" max="100" />
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <Checkbox id="allow_late" v-model:checked="form.allow_late_submission" />
                        <Label for="allow_late">Izinkan terlambat</Label>
                    </div>
                    <div v-if="form.allow_late_submission" class="ml-6 space-y-1">
                        <Label>Potongan Nilai (%)</Label>
                        <Input v-model.number="form.late_penalty_percent" type="number" min="0" max="100" class="w-32" />
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox id="is_published" v-model:checked="form.is_published" />
                    <Label for="is_published">Dipublikasikan</Label>
                </div>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}</Button>
                    <Button variant="outline" as-child><Link :href="`/guru/tugas/${assignment.id}`">Batal</Link></Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
