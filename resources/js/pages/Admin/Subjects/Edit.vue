<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import LoadingButton from '@/Components/LoadingButton.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { BookOpen } from 'lucide-vue-next';
import type { BreadcrumbItem, Department, Subject } from '@/types';

const props = defineProps<{
    subject: Subject;
    departments: Pick<Department, 'id' | 'name' | 'code'>[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Mata Pelajaran', href: '/admin/subjects' },
    { title: 'Edit', href: `/admin/subjects/${props.subject.id}/edit` },
];

const form = useForm({
    name: props.subject.name,
    code: props.subject.code,
    department_id: props.subject.department_id ? String(props.subject.department_id) : 'umum',
});

function submit() {
    form.transform((data) => ({
        ...data,
        department_id: data.department_id === 'umum' ? null : data.department_id,
    })).put(`/admin/subjects/${props.subject.id}`);
}
</script>

<template>
    <Head title="Edit Mata Pelajaran" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <PageHeader title="Edit Mata Pelajaran" :icon="BookOpen" />

            <Card class="max-w-2xl">
                <CardContent class="p-6">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name" class="font-semibold text-sm">Nama Mata Pelajaran <span class="text-destructive">*</span></Label>
                            <Input id="name" v-model="form.name" class="h-11" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="code" class="font-semibold text-sm">Kode <span class="text-destructive">*</span></Label>
                            <Input id="code" v-model="form.code" class="h-11 max-w-[200px]" />
                            <InputError :message="form.errors.code" />
                        </div>

                        <div class="space-y-2">
                            <Label for="department_id" class="font-semibold text-sm">Jurusan (opsional)</Label>
                            <Select v-model="form.department_id">
                                <SelectTrigger class="h-11">
                                    <SelectValue />
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

                        <div class="flex items-center justify-end gap-3 pt-4">
                            <Button variant="outline" type="button" as-child>
                                <Link href="/admin/subjects">Batal</Link>
                            </Button>
                            <LoadingButton type="submit" :loading="form.processing">
                                Simpan
                            </LoadingButton>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
