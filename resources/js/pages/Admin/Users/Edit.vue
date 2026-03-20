<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
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
import { Checkbox } from '@/components/ui/checkbox';
import type { BreadcrumbItem, User } from '@/types';

const props = defineProps<{
    user: User;
    roles: { value: string; label: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Manajemen Pengguna', href: '/admin/users' },
    { title: 'Edit Pengguna', href: `/admin/users/${props.user.id}/edit` },
];

const form = useForm({
    name: props.user.name,
    username: props.user.username,
    email: props.user.email ?? '',
    password: '',
    role: props.user.role,
    is_active: props.user.is_active,
});

function submit() {
    form.put(`/admin/users/${props.user.id}`);
}
</script>

<template>
    <Head title="Edit Pengguna" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <FlashMessage />

            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>Edit Pengguna: {{ user.name }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Nama</Label>
                            <Input id="name" v-model="form.name" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="username">Username (NIS/NIP)</Label>
                            <Input id="username" v-model="form.username" />
                            <InputError :message="form.errors.username" />
                        </div>

                        <div class="space-y-2">
                            <Label for="email">Email (opsional)</Label>
                            <Input id="email" v-model="form.email" type="email" />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password">Password (kosongkan jika tidak diubah)</Label>
                            <Input id="password" v-model="form.password" type="password" />
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="space-y-2">
                            <Label for="role">Role</Label>
                            <Select v-model="form.role">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="role in roles" :key="role.value" :value="role.value">
                                        {{ role.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.role" />
                        </div>

                        <div class="flex items-center gap-2">
                            <Checkbox id="is_active" v-model="form.is_active" />
                            <Label for="is_active">Aktif</Label>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <Button type="submit" :disabled="form.processing">
                                Perbarui
                            </Button>
                            <Button variant="outline" as-child>
                                <Link href="/admin/users">Batal</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
