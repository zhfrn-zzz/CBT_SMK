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
import type { BreadcrumbItem } from '@/types';

interface TeachingAssignment {
    id: number;
    classroom: { id: number; name: string };
    subject: { id: number; name: string };
}

const props = defineProps<{ teachingAssignments: TeachingAssignment[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tugas', href: '/guru/tugas' },
    { title: 'Buat Tugas', href: '/guru/tugas/create' },
];

const form = useForm({
    title: '',
    description: '',
    subject_id: '',
    classroom_id: '',
    file: null as File | null,
    deadline_at: '',
    max_score: 100,
    allow_late_submission: false,
    late_penalty_percent: 0,
    submission_type: 'file_or_text' as 'file' | 'text' | 'file_or_text',
    is_published: true,
});

const uniqueSubjects = computed(() => {
    const map = new Map<number, { id: number; name: string }>();
    props.teachingAssignments.forEach((a) => { if (!map.has(a.subject.id)) map.set(a.subject.id, a.subject); });
    return Array.from(map.values());
});

const filteredClassrooms = computed(() =>
    props.teachingAssignments.filter((a) => String(a.subject.id) === form.subject_id).map((a) => a.classroom),
);

watch(() => form.subject_id, () => { form.classroom_id = ''; });

function handleFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    form.file = target.files?.[0] ?? null;
}

function submit() {
    form.post('/guru/tugas', { forceFormData: true });
}
</script>

<template>
    <Head title="Buat Tugas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl p-4">
            <h2 class="mb-4 text-xl font-semibold">Buat Tugas</h2>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label>Mata Pelajaran <span class="text-destructive">*</span></Label>
                        <Select v-model="form.subject_id">
                            <SelectTrigger><SelectValue placeholder="Pilih Mata Pelajaran" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="s in uniqueSubjects" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.subject_id" class="text-xs text-destructive">{{ form.errors.subject_id }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label>Kelas <span class="text-destructive">*</span></Label>
                        <Select v-model="form.classroom_id" :disabled="!form.subject_id">
                            <SelectTrigger><SelectValue placeholder="Pilih Kelas" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="c in filteredClassrooms" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.classroom_id" class="text-xs text-destructive">{{ form.errors.classroom_id }}</p>
                    </div>
                </div>

                <div class="space-y-1">
                    <Label>Judul <span class="text-destructive">*</span></Label>
                    <Input v-model="form.title" />
                    <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>
                </div>

                <div class="space-y-1">
                    <Label>Deskripsi / Instruksi <span class="text-destructive">*</span></Label>
                    <Textarea v-model="form.description" rows="5" />
                    <p v-if="form.errors.description" class="text-xs text-destructive">{{ form.errors.description }}</p>
                </div>

                <div class="space-y-1">
                    <Label>Lampiran (opsional)</Label>
                    <Input type="file" @change="handleFileChange" />
                    <p class="text-xs text-muted-foreground">PDF, DOCX, PPTX, ZIP, dll. Maks 50 MB</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label>Deadline <span class="text-destructive">*</span></Label>
                        <Input v-model="form.deadline_at" type="datetime-local" />
                        <p v-if="form.errors.deadline_at" class="text-xs text-destructive">{{ form.errors.deadline_at }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label>Nilai Maksimal</Label>
                        <Input v-model.number="form.max_score" type="number" min="1" max="100" />
                    </div>
                </div>

                <div class="space-y-2">
                    <Label>Tipe Submission</Label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="form.submission_type" value="file" class="accent-primary" /> Upload File
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="form.submission_type" value="text" class="accent-primary" /> Teks
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="form.submission_type" value="file_or_text" class="accent-primary" /> File atau Teks
                        </label>
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
                    <Label for="is_published">Publish langsung</Label>
                </div>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Menyimpan...' : 'Buat Tugas' }}</Button>
                    <Button variant="outline" as-child><Link href="/guru/tugas">Batal</Link></Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
