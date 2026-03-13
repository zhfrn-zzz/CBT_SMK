<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { BreadcrumbItem, Subject } from '@/types';

defineProps<{
    subjects: Pick<Subject, 'id' | 'name' | 'code'>[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Bank Soal', href: '/guru/bank-soal' },
    { title: 'Buat', href: '/guru/bank-soal/create' },
];

const form = useForm({
    name: '',
    subject_id: '',
    description: '',
});

function submit() {
    form.post('/guru/bank-soal');
}
</script>

<template>
    <Head title="Buat Bank Soal" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>Buat Bank Soal</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Nama Bank Soal</Label>
                            <Input id="name" v-model="form.name" placeholder="Contoh: UTS ASJ Kelas XI Semester 1" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="subject_id">Mata Pelajaran</Label>
                            <Select v-model="form.subject_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih mata pelajaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="subject in subjects"
                                        :key="subject.id"
                                        :value="String(subject.id)"
                                    >
                                        {{ subject.name }} ({{ subject.code }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.subject_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="description">Deskripsi (opsional)</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                placeholder="Deskripsi singkat tentang bank soal ini"
                                rows="3"
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="flex gap-2 pt-2">
                            <Button type="submit" :disabled="form.processing">Simpan</Button>
                            <Button variant="outline" as-child>
                                <Link href="/guru/bank-soal">Batal</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
