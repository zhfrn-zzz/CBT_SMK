<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import type { BreadcrumbItem } from '@/types';

interface TeachingAssignment {
    id: number;
    classroom: { id: number; name: string };
    subject: { id: number; name: string };
}

const props = defineProps<{
    teachingAssignments: TeachingAssignment[];
    nextMeetingNumber: number;
    filters: { subject_id: number | null; classroom_id: number | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Presensi', href: '/guru/presensi' },
    { title: 'Buka Sesi', href: '/guru/presensi/create' },
];

const form = useForm({
    subject_id: String(props.filters.subject_id ?? ''),
    classroom_id: String(props.filters.classroom_id ?? ''),
    meeting_date: new Date().toISOString().slice(0, 10),
    meeting_number: props.nextMeetingNumber,
    duration_minutes: 30,
    note: '',
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

function submit() {
    form.post('/guru/presensi');
}
</script>

<template>
    <Head title="Buka Sesi Presensi" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-xl p-4">
            <h2 class="mb-4 text-xl font-semibold">Buka Sesi Presensi</h2>
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

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label>Tanggal</Label>
                        <Input v-model="form.meeting_date" type="date" />
                        <p v-if="form.errors.meeting_date" class="text-xs text-destructive">{{ form.errors.meeting_date }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label>Pertemuan Ke-</Label>
                        <Input v-model.number="form.meeting_number" type="number" min="1" />
                    </div>
                </div>

                <div class="space-y-1">
                    <Label>Durasi Kode Akses</Label>
                    <Select v-model.number="(form.duration_minutes as any)">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="(15 as any)">15 menit</SelectItem>
                            <SelectItem :value="(30 as any)">30 menit</SelectItem>
                            <SelectItem :value="(60 as any)">60 menit</SelectItem>
                            <SelectItem :value="(0 as any)">Sampai ditutup manual</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-1">
                    <Label>Catatan (opsional)</Label>
                    <Textarea v-model="form.note" rows="2" />
                </div>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Membuka...' : 'Buka Sesi Presensi' }}</Button>
                    <Button variant="outline" as-child><Link href="/guru/presensi">Batal</Link></Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
