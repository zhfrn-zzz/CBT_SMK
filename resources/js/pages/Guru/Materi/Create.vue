<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Card, CardContent } from '@/components/ui/card';
import { useYouTubeEmbed } from '@/composables/useYouTubeEmbed';
import type { BreadcrumbItem } from '@/types';

interface TeachingAssignment {
    id: number;
    classroom: { id: number; name: string };
    subject: { id: number; name: string };
}

const props = defineProps<{
    assignments: TeachingAssignment[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Materi', href: '/guru/materi' },
    { title: 'Tambah Materi', href: '/guru/materi/create' },
];

const form = useForm({
    title: '',
    description: '',
    subject_id: '',
    classroom_id: '',
    type: 'file' as 'file' | 'video_link' | 'text',
    file: null as File | null,
    video_url: '',
    text_content: '',
    topic: '',
    order: 0,
    is_published: true,
});

const uniqueSubjects = computed(() => {
    const map = new Map<number, { id: number; name: string }>();
    props.assignments.forEach((a) => {
        if (!map.has(a.subject.id)) map.set(a.subject.id, a.subject);
    });
    return Array.from(map.values());
});

const filteredClassrooms = computed(() =>
    props.assignments
        .filter((a) => String(a.subject.id) === form.subject_id)
        .map((a) => a.classroom),
);

watch(() => form.subject_id, () => {
    form.classroom_id = '';
});

const { embedUrl } = useYouTubeEmbed(computed(() => form.video_url).value);
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
    form.post('/guru/materi', {
        forceFormData: true,
    });
}
</script>

<template>
    <Head title="Tambah Materi" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl p-4">
            <h2 class="mb-4 text-xl font-semibold">Tambah Materi</h2>

            <form @submit.prevent="submit" class="space-y-4">
                <!-- Mapel & Kelas -->
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

                <!-- Judul -->
                <div class="space-y-1">
                    <Label>Judul <span class="text-destructive">*</span></Label>
                    <Input v-model="form.title" placeholder="Judul materi" />
                    <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>
                </div>

                <!-- Deskripsi -->
                <div class="space-y-1">
                    <Label>Deskripsi</Label>
                    <Textarea v-model="form.description" placeholder="Deskripsi singkat materi (opsional)" rows="2" />
                </div>

                <!-- Topik & Urutan -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label>Topik / Bab</Label>
                        <Input v-model="form.topic" placeholder="Contoh: Bab 1 - Pengenalan" />
                    </div>
                    <div class="space-y-1">
                        <Label>Urutan</Label>
                        <Input v-model.number="form.order" type="number" min="0" />
                    </div>
                </div>

                <!-- Tipe Materi -->
                <div class="space-y-2">
                    <Label>Tipe Materi <span class="text-destructive">*</span></Label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="form.type" value="file" class="accent-primary" />
                            File Upload
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="form.type" value="video_link" class="accent-primary" />
                            Link Video YouTube
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="form.type" value="text" class="accent-primary" />
                            Teks / Artikel
                        </label>
                    </div>
                </div>

                <!-- File Upload -->
                <div v-if="form.type === 'file'" class="space-y-1">
                    <Label>File <span class="text-destructive">*</span></Label>
                    <Input type="file" accept=".pdf,.docx,.pptx,.doc,.ppt,.xls,.xlsx,.jpg,.jpeg,.png,.gif" @change="handleFileChange" />
                    <p class="text-xs text-muted-foreground">Maks 50 MB. Format: PDF, DOCX, PPTX, XLS, XLSX, JPG, PNG, GIF</p>
                    <p v-if="form.errors.file" class="text-xs text-destructive">{{ form.errors.file }}</p>
                </div>

                <!-- Video URL -->
                <div v-if="form.type === 'video_link'" class="space-y-2">
                    <div class="space-y-1">
                        <Label>URL YouTube <span class="text-destructive">*</span></Label>
                        <Input v-model="form.video_url" placeholder="https://www.youtube.com/watch?v=..." />
                        <p v-if="form.errors.video_url" class="text-xs text-destructive">{{ form.errors.video_url }}</p>
                    </div>
                    <div v-if="videoPreviewUrl" class="aspect-video w-full overflow-hidden rounded-md border">
                        <iframe :src="videoPreviewUrl" class="size-full" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen />
                    </div>
                </div>

                <!-- Text Content -->
                <div v-if="form.type === 'text'" class="space-y-1">
                    <Label>Konten Teks <span class="text-destructive">*</span></Label>
                    <Textarea v-model="form.text_content" placeholder="Tulis konten materi di sini..." rows="10" />
                    <p v-if="form.errors.text_content" class="text-xs text-destructive">{{ form.errors.text_content }}</p>
                </div>

                <!-- Publish -->
                <div class="flex items-center gap-2">
                    <Checkbox id="is_published" v-model="form.is_published" />
                    <Label for="is_published">Publish langsung</Label>
                </div>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Materi' }}
                    </Button>
                    <Button variant="outline" as-child>
                        <Link href="/guru/materi">Batal</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
