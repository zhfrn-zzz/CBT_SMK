<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import { GraduationCap, Plus, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem, Subject } from '@/types';

type ClassroomOption = { id: number; name: string; department: string | null };
type TeachingRow = { classroom_id: number | null; subject_id: number | null };

type GuruData = {
    id: number;
    name: string;
    username: string;
    email: string | null;
    phone: string | null;
    is_active: boolean;
    teachings: { classroom_id: number; subject_id: number; classroom_name?: string; subject_name?: string }[];
};

const props = defineProps<{
    guru: GuruData;
    classrooms: ClassroomOption[];
    subjects: Subject[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Data Guru', href: '/admin/guru' },
    { title: 'Edit Guru', href: `/admin/guru/${props.guru.id}/edit` },
];

const form = useForm({
    name: props.guru.name,
    username: props.guru.username,
    email: props.guru.email ?? '',
    phone: props.guru.phone ?? '',
    password: '',
    is_active: props.guru.is_active,
    teachings: props.guru.teachings.map(t => ({
        classroom_id: t.classroom_id as number | null,
        subject_id: t.subject_id as number | null,
    })) as TeachingRow[],
});

function addTeaching() {
    form.teachings.push({ classroom_id: null, subject_id: null });
}

function removeTeaching(index: number) {
    form.teachings.splice(index, 1);
}

function submit() {
    form.put(`/admin/guru/${props.guru.id}`);
}
</script>

<template>
    <Head :title="`Edit Guru - ${guru.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <PageHeader title="Edit Guru" :icon="GraduationCap">
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link href="/admin/guru">Kembali</Link>
                    </Button>
                </template>
            </PageHeader>

            <form @submit.prevent="submit" class="space-y-6 max-w-2xl">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm">Informasi Guru</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="name">Nama <span class="text-red-500">*</span></Label>
                                <Input id="name" v-model="form.name" placeholder="Nama lengkap" />
                                <InputError :message="form.errors.name" />
                            </div>
                            <div class="space-y-2">
                                <Label for="username">NIP <span class="text-red-500">*</span></Label>
                                <Input id="username" v-model="form.username" placeholder="Nomor Induk Pegawai" />
                                <InputError :message="form.errors.username" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="email">Email</Label>
                                <Input id="email" v-model="form.email" type="email" placeholder="email@sekolah.id" />
                                <InputError :message="form.errors.email" />
                            </div>
                            <div class="space-y-2">
                                <Label for="phone">Telepon</Label>
                                <Input id="phone" v-model="form.phone" placeholder="08xxxxxxxxxx" />
                                <InputError :message="form.errors.phone" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="password">Password Baru</Label>
                            <Input id="password" v-model="form.password" type="password" placeholder="Kosongkan jika tidak ingin mengubah" />
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="flex items-center space-x-2">
                            <Checkbox id="is_active" :checked="form.is_active" @update:checked="(val: boolean) => form.is_active = val" />
                            <Label for="is_active">Akun aktif</Label>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm flex items-center justify-between">
                            Penugasan Mengajar
                            <Button type="button" variant="outline" size="sm" @click="addTeaching">
                                <Plus class="size-4 mr-1" />Tambah
                            </Button>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div v-if="form.teachings.length === 0" class="text-sm text-muted-foreground text-center py-4">
                            Belum ada penugasan mengajar. Klik "Tambah" untuk menambahkan.
                        </div>
                        <div
                            v-for="(teaching, index) in form.teachings"
                            :key="index"
                            class="flex items-end gap-3"
                        >
                            <div class="flex-1 space-y-1">
                                <Label :for="`classroom-${index}`" class="text-xs">Kelas</Label>
                                <Select v-model="teaching.classroom_id as unknown as string" @update:model-value="(v: string) => teaching.classroom_id = Number(v)">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih Kelas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="c in classrooms" :key="c.id" :value="String(c.id)">
                                            {{ c.name }} {{ c.department ? `(${c.department})` : '' }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors[`teachings.${index}.classroom_id`]" />
                            </div>
                            <div class="flex-1 space-y-1">
                                <Label :for="`subject-${index}`" class="text-xs">Mata Pelajaran</Label>
                                <Select v-model="teaching.subject_id as unknown as string" @update:model-value="(v: string) => teaching.subject_id = Number(v)">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih Mapel" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="s in subjects" :key="s.id" :value="String(s.id)">
                                            {{ s.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors[`teachings.${index}.subject_id`]" />
                            </div>
                            <Button type="button" variant="ghost" size="icon" @click="removeTeaching(index)">
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <div class="flex justify-end gap-3">
                    <Button variant="outline" as-child>
                        <Link href="/admin/guru">Batal</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
