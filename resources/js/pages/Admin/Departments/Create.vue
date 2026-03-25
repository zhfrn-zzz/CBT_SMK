<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import LoadingButton from '@/Components/LoadingButton.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Building2 } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Jurusan', href: '/admin/departments' },
    { title: 'Tambah', href: '/admin/departments/create' },
];

const form = useForm({
    name: '',
    code: '',
});

function submit() {
    form.post('/admin/departments');
}
</script>

<template>
    <Head title="Tambah Jurusan" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <PageHeader title="Tambah Jurusan" :icon="Building2" />

            <Card class="max-w-2xl">
                <CardContent class="p-6">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name" class="font-semibold text-sm">Nama Jurusan <span class="text-destructive">*</span></Label>
                            <Input id="name" v-model="form.name" placeholder="Contoh: Teknik Komputer dan Jaringan" class="h-11" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="code" class="font-semibold text-sm">Kode Jurusan <span class="text-destructive">*</span></Label>
                            <Input id="code" v-model="form.code" placeholder="Contoh: TKJ" class="h-11 max-w-[200px]" />
                            <InputError :message="form.errors.code" />
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4">
                            <Button variant="outline" as-child>
                                <Link href="/admin/departments">Batal</Link>
                            </Button>
                            <LoadingButton :loading="form.processing" type="submit">Simpan</LoadingButton>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
