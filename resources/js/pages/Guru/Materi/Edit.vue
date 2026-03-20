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
import type { BreadcrumbItem, Material } from '@/types';

interface TeachingAssignment {
    id: number;
    classroom: { id: number; name: string };
    subject: { id: number; name: string };
}

const props = defineProps<{
    material: Material;
    assignments: TeachingAssignment[];
    topics: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Materi', href: '/guru/materi' },
    { title: props.material.title, href: `/guru/materi/${props.material.id}` },
    { title: 'Edit', href: `/guru/materi/${props.material.id}/edit` },
];

const form = useForm({
    title: props.material.title,
    description: props.material.description ?? '',
    subject_id: String(props.material.subject_id),
    classroom_id: String(props.material.classroom_id),
    type: props.material.type,
    file: null as File | null,
    video_url: props.material.video_url ?? '',
    text_content: props.material.text_content ?? '',
    topic: props.material.topic ?? '',
    order: props.material.order,
    is_published: props.material.is_published,
    _method: 'PUT',
});

const uniqueSubjects = computed(() => {
    const map = new Map<number, { id: number; name: string }>();
    props.assignments.forEach((a) => {
        if (!map.has(a.subject.id)) map.set(a.subject.id, a.subject);
    });
    return Array.from(map.values());
});

const filteredClassrooms = computed(() =>
    props.assignments.filter((a) => String(a.subject.id) === form.subject_id).map((a) => a.classroom),
);

const videoPreviewUrl = computed(() => {
    if (!form.video_url) return null;
    const regex = /(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/;
    const match = form.video_url.match(regex);
    return match ? `https://www.youtube.com/embed/${match[1]}` : null;
});

function handleFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    form.file = target.files?.[0] ?? null;
}

function submit() {
    form.post(`/guru/materi/${props.material.id}`, { forceFormData: true });
}
</script>

<template>
    <Head :title="`Edit: ${material.title}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl p-4">
            <h2 class="mb-4 text-xl font-semibold">Edit Materi</h2>

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
                    </div>
                    <div class="space-y-1">
                        <Label>Kelas <span class="text-destructive">*</span></Label>
                        <Select v-model="form.classroom_id" :disabled="!form.subject_id">
                            <SelectTrigger><SelectValue placeholder="Pilih Kelas" /></SelectTrigger>
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
                    <Label>Deskripsi</Label>
                    <Textarea v-model="form.description" rows="2" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label>Topik / Bab</Label>
                        <Input v-model="form.topic" list="topics-list" />
                        <datalist id="topics-list">
                            <option v-for="t in topics" :key="t" :value="t" />
                        </datalist>
                    </div>
                    <div class="space-y-1">
                        <Label>Urutan</Label>
                        <Input v-model.number="form.order" type="number" min="0" />
                    </div>
                </div>

                <!-- File existing info -->
                <div v-if="material.type === 'file' && material.file_original_name" class="rounded-md border p-3 text-sm">
                    <p class="font-medium">File saat ini: {{ material.file_original_name }}</p>
                    <p v-if="material.formatted_file_size" class="text-muted-foreground">{{ material.formatted_file_size }}</p>
                    <p class="mt-1 text-muted-foreground">Upload file baru untuk mengganti:</p>
                    <Input type="file" accept=".pdf,.docx,.pptx,.doc,.ppt,.xls,.xlsx,.jpg,.jpeg,.png,.gif" class="mt-1" @change="handleFileChange" />
                </div>

                <!-- Video URL -->
                <div v-if="form.type === 'video_link'" class="space-y-2">
                    <div class="space-y-1">
                        <Label>URL YouTube <span class="text-destructive">*</span></Label>
                        <Input v-model="form.video_url" placeholder="https://www.youtube.com/watch?v=..." />
                        <p v-if="form.errors.video_url" class="text-xs text-destructive">{{ form.errors.video_url }}</p>
                    </div>
                    <div v-if="videoPreviewUrl" class="aspect-video w-full overflow-hidden rounded-md border">
                        <iframe :src="videoPreviewUrl" class="size-full" allowfullscreen />
                    </div>
                </div>

                <!-- Text Content -->
                <div v-if="form.type === 'text'" class="space-y-1">
                    <Label>Konten Teks <span class="text-destructive">*</span></Label>
                    <Textarea v-model="form.text_content" rows="10" />
                    <p v-if="form.errors.text_content" class="text-xs text-destructive">{{ form.errors.text_content }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox id="is_published" v-model="form.is_published" />
                    <Label for="is_published">Dipublikasikan</Label>
                </div>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="`/guru/materi/${material.id}`">Batal</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
