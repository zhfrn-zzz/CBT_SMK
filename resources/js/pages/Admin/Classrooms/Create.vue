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
import type { AcademicYear, BreadcrumbItem, Department } from '@/types';

defineProps<{
    academicYears: Pick<AcademicYear, 'id' | 'name' | 'semester'>[];
    departments: Pick<Department, 'id' | 'name' | 'code'>[];
    gradeLevels: { value: string; label: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Kelas', href: '/admin/classrooms' },
    { title: 'Tambah', href: '/admin/classrooms/create' },
];

const form = useForm({
    name: '',
    academic_year_id: '',
    department_id: '',
    grade_level: '',
});

function submit() {
    form.post('/admin/classrooms');
}
</script>

<template>
    <Head title="Tambah Kelas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>Tambah Kelas</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Nama Kelas</Label>
                            <Input id="name" v-model="form.name" placeholder="Contoh: XI TKJ 1" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="academic_year_id">Tahun Ajaran</Label>
                            <Select v-model="form.academic_year_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih tahun ajaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="ay in academicYears"
                                        :key="ay.id"
                                        :value="String(ay.id)"
                                    >
                                        {{ ay.name }} ({{ ay.semester }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.academic_year_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="department_id">Jurusan</Label>
                            <Select v-model="form.department_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih jurusan" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="dept in departments"
                                        :key="dept.id"
                                        :value="String(dept.id)"
                                    >
                                        {{ dept.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.department_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="grade_level">Tingkat</Label>
                            <Select v-model="form.grade_level">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih tingkat" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="gl in gradeLevels"
                                        :key="gl.value"
                                        :value="gl.value"
                                    >
                                        {{ gl.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.grade_level" />
                        </div>

                        <div class="flex gap-2 pt-2">
                            <Button type="submit" :disabled="form.processing">Simpan</Button>
                            <Button variant="outline" as-child>
                                <Link href="/admin/classrooms">Batal</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
