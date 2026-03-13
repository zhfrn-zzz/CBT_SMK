<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { BreadcrumbItem, Department } from '@/types';

defineProps<{
    departments: Pick<Department, 'id' | 'name' | 'code'>[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Mata Pelajaran', href: '/admin/subjects' },
    { title: 'Tambah', href: '/admin/subjects/create' },
];

const form = useForm({
    name: '',
    code: '',
    department_id: '',
});

function submit() {
    form.transform((data) => ({
        ...data,
        department_id: data.department_id === 'umum' ? null : data.department_id,
    })).post('/admin/subjects');
}
</script>

<template>
    <Head title="Tambah Mata Pelajaran" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>Tambah Mata Pelajaran</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Nama Mata Pelajaran</Label>
                            <Input id="name" v-model="form.name" placeholder="Contoh: Administrasi Sistem Jaringan" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="code">Kode</Label>
                            <Input id="code" v-model="form.code" placeholder="Contoh: ASJ" class="max-w-[200px]" />
                            <InputError :message="form.errors.code" />
                        </div>

                        <div class="space-y-2">
                            <Label for="department_id">Jurusan (opsional)</Label>
                            <Select v-model="form.department_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih jurusan (kosongkan untuk mapel umum)" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="umum">Umum (tidak terkait jurusan)</SelectItem>
                                    <SelectItem
                                        v-for="dept in departments"
                                        :key="dept.id"
                                        :value="String(dept.id)"
                                    >
                                        {{ dept.name }} ({{ dept.code }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.department_id" />
                        </div>

                        <div class="flex gap-2 pt-2">
                            <Button type="submit" :disabled="form.processing">Simpan</Button>
                            <Button variant="outline" as-child>
                                <Link href="/admin/subjects">Batal</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
