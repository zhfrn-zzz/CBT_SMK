<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import LoadingButton from '@/Components/LoadingButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { FileText } from 'lucide-vue-next';
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
            <PageHeader title="Tambah Tugas" :icon="FileText" />

            <Card class="mt-4">
                <CardContent class="p-6">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1">
                                <Label class="font-semibold text-sm">Mata Pelajaran <span class="text-destructive">*</span></Label>
                                <Select v-model="form.subject_id">
                                    <SelectTrigger class="h-11"><SelectValue placeholder="Pilih Mata Pelajaran" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="s in uniqueSubjects" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.subject_id" class="text-xs text-destructive">{{ form.errors.subject_id }}</p>
                            </div>
                            <div class="space-y-1">
                                <Label class="font-semibold text-sm">Kelas <span class="text-destructive">*</span></Label>
                                <Select v-model="form.classroom_id" :disabled="!form.subject_id">
                                    <SelectTrigger class="h-11"><SelectValue placeholder="Pilih Kelas" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="c in filteredClassrooms" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.classroom_id" class="text-xs text-destructive">{{ form.errors.classroom_id }}</p>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <Label class="font-semibold text-sm">Judul <span class="text-destructive">*</span></Label>
                            <Input v-model="form.title" class="h-11" />
                            <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>
                        </div>

                        <div class="space-y-1">
                            <Label class="font-semibold text-sm">Deskripsi / Instruksi <span class="text-destructive">*</span></Label>
                            <Textarea v-model="form.description" rows="5" />
                            <p v-if="form.errors.description" class="text-xs text-destructive">{{ form.errors.description }}</p>
                        </div>

                        <div class="space-y-1">
                            <Label class="font-semibold text-sm">Lampiran (opsional)</Label>
                            <Input type="file" @change="handleFileChange" class="h-11" />
                            <p class="text-xs text-muted-foreground">PDF, DOCX, PPTX, ZIP, dll. Maks 50 MB</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1">
                                <Label class="font-semibold text-sm">Deadline <span class="text-destructive">*</span></Label>
                                <Input v-model="form.deadline_at" type="datetime-local" class="h-11" />
                                <p v-if="form.errors.deadline_at" class="text-xs text-destructive">{{ form.errors.deadline_at }}</p>
                            </div>
                            <div class="space-y-1">
                                <Label class="font-semibold text-sm">Nilai Maksimal</Label>
                                <Input v-model.number="form.max_score" type="number" min="1" max="100" class="h-11" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label class="font-semibold text-sm">Tipe Submission</Label>
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
                                <Checkbox id="allow_late" v-model="form.allow_late_submission" />
                                <Label for="allow_late">Izinkan terlambat</Label>
                            </div>
                            <div v-if="form.allow_late_submission" class="ml-6 space-y-1">
                                <Label class="font-semibold text-sm">Potongan Nilai (%)</Label>
                                <Input v-model.number="form.late_penalty_percent" type="number" min="0" max="100" class="w-32 h-11" />
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Checkbox id="is_published" v-model="form.is_published" />
                            <Label for="is_published">Publish langsung</Label>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4">
                            <Button variant="outline" as-child><Link href="/guru/tugas">Batal</Link></Button>
                            <LoadingButton :loading="form.processing" type="submit">Simpan</LoadingButton>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
