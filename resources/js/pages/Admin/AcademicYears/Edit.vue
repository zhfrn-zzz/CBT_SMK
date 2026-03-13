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
import { Checkbox } from '@/components/ui/checkbox';
import type { AcademicYear, BreadcrumbItem } from '@/types';

const props = defineProps<{
    academicYear: AcademicYear;
    semesters: { value: string; label: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Tahun Ajaran', href: '/admin/academic-years' },
    { title: 'Edit', href: `/admin/academic-years/${props.academicYear.id}/edit` },
];

// dates come as ISO strings from backend cast — extract YYYY-MM-DD for input[type=date]
function toDateString(val: string): string {
    if (!val) return '';
    return val.substring(0, 10);
}

const form = useForm({
    name: props.academicYear.name,
    semester: props.academicYear.semester,
    is_active: props.academicYear.is_active,
    starts_at: toDateString(props.academicYear.starts_at),
    ends_at: toDateString(props.academicYear.ends_at),
});

function submit() {
    form.transform((data) => ({
        ...data,
        is_active: !!data.is_active,
    })).put(`/admin/academic-years/${props.academicYear.id}`);
}
</script>

<template>
    <Head title="Edit Tahun Ajaran" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>Edit Tahun Ajaran: {{ academicYear.name }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Nama Tahun Ajaran</Label>
                            <Input id="name" v-model="form.name" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="semester">Semester</Label>
                            <Select v-model="form.semester">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in semesters" :key="s.value" :value="s.value">
                                        {{ s.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.semester" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="starts_at">Tanggal Mulai</Label>
                                <Input id="starts_at" v-model="form.starts_at" type="date" />
                                <InputError :message="form.errors.starts_at" />
                            </div>
                            <div class="space-y-2">
                                <Label for="ends_at">Tanggal Selesai</Label>
                                <Input id="ends_at" v-model="form.ends_at" type="date" />
                                <InputError :message="form.errors.ends_at" />
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Checkbox id="is_active" :checked="form.is_active" @update:checked="form.is_active = $event" />
                            <Label for="is_active">Aktifkan (tahun ajaran lain akan dinonaktifkan)</Label>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <Button type="submit" :disabled="form.processing">Perbarui</Button>
                            <Button variant="outline" as-child>
                                <Link href="/admin/academic-years">Batal</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
