<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { Database } from 'lucide-vue-next';
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
            <PageHeader title="Tambah Bank Soal" :icon="Database" />

            <Card class="max-w-2xl">
                <CardContent class="p-6">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name" class="font-semibold text-sm">
                                Nama Bank Soal <span class="text-destructive">*</span>
                            </Label>
                            <Input id="name" v-model="form.name" class="h-11" placeholder="Contoh: UTS ASJ Kelas XI Semester 1" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="subject_id" class="font-semibold text-sm">
                                Mata Pelajaran <span class="text-destructive">*</span>
                            </Label>
                            <Select v-model="form.subject_id">
                                <SelectTrigger class="h-11">
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
                            <Label for="description" class="font-semibold text-sm">Deskripsi (opsional)</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                placeholder="Deskripsi singkat tentang bank soal ini"
                                rows="3"
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4">
                            <Button variant="outline" as-child>
                                <Link href="/guru/bank-soal">Batal</Link>
                            </Button>
                            <LoadingButton :loading="form.processing" type="submit">Simpan</LoadingButton>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
