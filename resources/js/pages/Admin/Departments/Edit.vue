<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem, Department } from '@/types';

const props = defineProps<{
    department: Department;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Jurusan', href: '/admin/departments' },
    { title: 'Edit', href: `/admin/departments/${props.department.id}/edit` },
];

const form = useForm({
    name: props.department.name,
    code: props.department.code,
});

function submit() {
    form.put(`/admin/departments/${props.department.id}`);
}
</script>

<template>
    <Head title="Edit Jurusan" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>Edit Jurusan: {{ department.name }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Nama Jurusan</Label>
                            <Input id="name" v-model="form.name" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="code">Kode Jurusan</Label>
                            <Input id="code" v-model="form.code" class="max-w-[200px]" />
                            <InputError :message="form.errors.code" />
                        </div>

                        <div class="flex gap-2 pt-2">
                            <Button type="submit" :disabled="form.processing">Perbarui</Button>
                            <Button variant="outline" as-child>
                                <Link href="/admin/departments">Batal</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
