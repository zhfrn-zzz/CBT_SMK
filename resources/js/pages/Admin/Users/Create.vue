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
import { Separator } from '@/components/ui/separator';
import type { BreadcrumbItem } from '@/types';

defineProps<{
    roles: { value: string; label: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Manajemen Pengguna', href: '/admin/users' },
    { title: 'Tambah Pengguna', href: '/admin/users/create' },
];

const form = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
    role: 'siswa',
    is_active: true,
});

const importForm = useForm({
    file: null as File | null,
});

function submit() {
    form.post('/admin/users');
}

function submitImport() {
    if (!importForm.file) return;
    importForm.post('/admin/users/import', {
        forceFormData: true,
    });
}

function handleFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    importForm.file = target.files?.[0] ?? null;
}
</script>

<template>
    <Head title="Tambah Pengguna" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <FlashMessage />

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Form Tambah Manual -->
                <Card>
                    <CardHeader>
                        <CardTitle>Tambah Pengguna</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="name">Nama</Label>
                                <Input id="name" v-model="form.name" placeholder="Nama lengkap" />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="username">Username (NIS/NIP)</Label>
                                <Input id="username" v-model="form.username" placeholder="NIS atau NIP" />
                                <InputError :message="form.errors.username" />
                            </div>

                            <div class="space-y-2">
                                <Label for="email">Email (opsional)</Label>
                                <Input id="email" v-model="form.email" type="email" placeholder="email@contoh.com" />
                                <InputError :message="form.errors.email" />
                            </div>

                            <div class="space-y-2">
                                <Label for="password">Password</Label>
                                <Input id="password" v-model="form.password" type="password" placeholder="Minimal 8 karakter" />
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
                                <Checkbox id="is_active" :checked="form.is_active" @update:checked="form.is_active = $event" />
                                <Label for="is_active">Aktif</Label>
                            </div>

                            <div class="flex gap-2 pt-2">
                                <Button type="submit" :disabled="form.processing">
                                    Simpan
                                </Button>
                                <Button variant="outline" as-child>
                                    <Link href="/admin/users">Batal</Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Form Import Excel -->
                <Card>
                    <CardHeader>
                        <CardTitle>Import Siswa dari Excel</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <p class="text-sm text-muted-foreground">
                                Upload file Excel (.xlsx, .xls) atau CSV dengan kolom:
                                <strong>nis</strong>, <strong>nama</strong>, <strong>email</strong> (opsional), <strong>password</strong> (opsional).
                            </p>

                            <Separator />

                            <form @submit.prevent="submitImport" class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="import-file">File Excel/CSV</Label>
                                    <Input
                                        id="import-file"
                                        type="file"
                                        accept=".xlsx,.xls,.csv"
                                        @change="handleFileChange"
                                    />
                                    <InputError :message="importForm.errors.file" />
                                </div>

                                <Button type="submit" :disabled="importForm.processing || !importForm.file">
                                    Import
                                </Button>
                            </form>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
