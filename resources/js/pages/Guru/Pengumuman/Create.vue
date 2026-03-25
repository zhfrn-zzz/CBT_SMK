<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { Megaphone } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface TeachingAssignment {
    id: number;
    classroom: { id: number; name: string };
    subject: { id: number; name: string };
}

const props = defineProps<{ teachingAssignments: TeachingAssignment[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pengumuman', href: '/guru/pengumuman' },
    { title: 'Buat Pengumuman', href: '/guru/pengumuman/create' },
];

const form = useForm({
    title: '',
    content: '',
    classroom_id: '',
    subject_id: '',
    is_pinned: false,
    published_at: new Date().toISOString().slice(0, 16),
});

const uniqueClassrooms = computed(() => {
    const map = new Map<number, { id: number; name: string }>();
    props.teachingAssignments.forEach((a) => { if (!map.has(a.classroom.id)) map.set(a.classroom.id, a.classroom); });
    return Array.from(map.values());
});

function submit() {
    form.transform((data) => ({
        ...data,
        classroom_id: data.classroom_id === 'all' ? '' : data.classroom_id,
    })).post('/guru/pengumuman');
}
</script>

<template>
    <Head title="Buat Pengumuman" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl p-4">
            <PageHeader title="Buat Pengumuman" :icon="Megaphone" />

            <Card class="mt-4">
                <CardContent class="p-6">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-1">
                            <Label class="font-semibold text-sm">Judul <span class="text-destructive">*</span></Label>
                            <Input v-model="form.title" class="h-11" />
                            <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>
                        </div>

                        <div class="space-y-1">
                            <Label class="font-semibold text-sm">Isi Pengumuman <span class="text-destructive">*</span></Label>
                            <Textarea v-model="form.content" rows="6" />
                            <p v-if="form.errors.content" class="text-xs text-destructive">{{ form.errors.content }}</p>
                        </div>

                        <div class="space-y-1">
                            <Label class="font-semibold text-sm">Target Kelas</Label>
                            <Select v-model="form.classroom_id">
                                <SelectTrigger class="h-11"><SelectValue placeholder="Semua Kelas (Broadcast)" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Kelas</SelectItem>
                                    <SelectItem v-for="c in uniqueClassrooms" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-1">
                            <Label class="font-semibold text-sm">Jadwal Publish</Label>
                            <Input v-model="form.published_at" type="datetime-local" class="h-11" />
                        </div>

                        <div class="flex items-center gap-2">
                            <Checkbox id="is_pinned" v-model="form.is_pinned" />
                            <Label for="is_pinned">Pin pengumuman</Label>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4">
                            <Button variant="outline" as-child><Link href="/guru/pengumuman">Batal</Link></Button>
                            <LoadingButton :loading="form.processing" type="submit">Publish</LoadingButton>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
