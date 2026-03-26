<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import LoadingButton from '@/Components/LoadingButton.vue';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import { Plus, Trash2, UserPlus } from 'lucide-vue-next';
import type { AcademicYear, Classroom, Department, Subject, BreadcrumbItem } from '@/types';

const props = defineProps<{
    roles: { value: string; label: string }[];
    academicYears: AcademicYear[];
    departments: Department[];
    classrooms: Classroom[];
    subjects: Subject[];
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
    classroom_id: null as number | null,
    teachings: [] as { classroom_id: number | null; subject_id: number | null }[],
});

const importForm = useForm({
    file: null as File | null,
});

// Siswa: cascading dropdown state
const selectedAcademicYearId = ref<number | null>(null);
const selectedDepartmentId = ref<number | null>(null);

const filteredClassroomsForSiswa = computed(() => {
    return props.classrooms.filter((c) => {
        if (selectedAcademicYearId.value && c.academic_year_id !== selectedAcademicYearId.value) return false;
        if (selectedDepartmentId.value && c.department_id !== selectedDepartmentId.value) return false;
        return true;
    });
});

// Reset downstream when upstream changes
watch(selectedAcademicYearId, () => {
    selectedDepartmentId.value = null;
    form.classroom_id = null;
});

watch(selectedDepartmentId, () => {
    form.classroom_id = null;
});

// Reset assignment fields when role changes
watch(() => form.role, () => {
    form.classroom_id = null;
    form.teachings = [];
    selectedAcademicYearId.value = null;
    selectedDepartmentId.value = null;
});

// Guru: teaching assignment helpers
const guruSelectedAcademicYearId = ref<number | null>(null);
const guruSelectedDepartmentId = ref<number | null>(null);

const filteredClassroomsForGuru = computed(() => {
    return props.classrooms.filter((c) => {
        if (guruSelectedAcademicYearId.value && c.academic_year_id !== guruSelectedAcademicYearId.value) return false;
        if (guruSelectedDepartmentId.value && c.department_id !== guruSelectedDepartmentId.value) return false;
        return true;
    });
});

const filteredSubjectsForGuru = computed(() => {
    if (!guruSelectedDepartmentId.value) return props.subjects;
    return props.subjects.filter(
        (s) => !s.department_id || s.department_id === guruSelectedDepartmentId.value,
    );
});

function addTeaching() {
    form.teachings.push({ classroom_id: null, subject_id: null });
}

function removeTeaching(index: number) {
    form.teachings.splice(index, 1);
}

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
        <div class="flex h-full flex-1 flex-col gap-6 rounded-lg p-4">
            <FlashMessage />

            <PageHeader title="Tambah Pengguna" :icon="UserPlus" />

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Form Tambah Manual -->
                <Card>
                    <CardContent class="p-6">
                        <form @submit.prevent="submit" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="name" class="font-semibold text-sm">Nama <span class="text-destructive">*</span></Label>
                                <Input id="name" v-model="form.name" placeholder="Nama lengkap" class="h-11" />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="username" class="font-semibold text-sm">Username (NIS/NIP) <span class="text-destructive">*</span></Label>
                                <Input id="username" v-model="form.username" placeholder="NIS atau NIP" class="h-11" />
                                <InputError :message="form.errors.username" />
                            </div>

                            <div class="space-y-2">
                                <Label for="email" class="font-semibold text-sm">Email (opsional)</Label>
                                <Input id="email" v-model="form.email" type="email" placeholder="email@contoh.com" class="h-11" />
                                <InputError :message="form.errors.email" />
                            </div>

                            <div class="space-y-2">
                                <Label for="password" class="font-semibold text-sm">Password <span class="text-destructive">*</span></Label>
                                <Input id="password" v-model="form.password" type="password" placeholder="Minimal 8 karakter" class="h-11" />
                                <InputError :message="form.errors.password" />
                            </div>

                            <div class="space-y-2">
                                <Label for="role" class="font-semibold text-sm">Role <span class="text-destructive">*</span></Label>
                                <Select v-model="form.role">
                                    <SelectTrigger class="h-11">
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
                                <Label for="is_active" class="font-semibold text-sm">Aktif</Label>
                            </div>

                            <!-- Siswa: Cascading Dropdown (Tahun Ajaran → Jurusan → Kelas) -->
                            <template v-if="form.role === 'siswa'">
                                <Separator />
                                <p class="text-sm font-medium">Penempatan Kelas</p>

                                <div class="space-y-2">
                                    <Label class="font-semibold text-sm">Tahun Ajaran</Label>
                                    <Select v-model="selectedAcademicYearId">
                                        <SelectTrigger class="h-11">
                                            <SelectValue placeholder="Pilih tahun ajaran" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="ay in academicYears"
                                                :key="ay.id"
                                                :value="ay.id"
                                            >
                                                {{ ay.name }} ({{ ay.semester }})
                                                <Badge v-if="ay.is_active" variant="default" class="ml-1 text-xs">Aktif</Badge>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="space-y-2">
                                    <Label class="font-semibold text-sm">Jurusan</Label>
                                    <Select v-model="selectedDepartmentId" :disabled="!selectedAcademicYearId">
                                        <SelectTrigger class="h-11">
                                            <SelectValue placeholder="Pilih jurusan" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="dept in departments"
                                                :key="dept.id"
                                                :value="dept.id"
                                            >
                                                {{ dept.name }} ({{ dept.code }})
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="space-y-2">
                                    <Label class="font-semibold text-sm">Kelas</Label>
                                    <Select v-model="form.classroom_id" :disabled="!selectedDepartmentId">
                                        <SelectTrigger class="h-11">
                                            <SelectValue placeholder="Pilih kelas" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="cls in filteredClassroomsForSiswa"
                                                :key="cls.id"
                                                :value="cls.id"
                                            >
                                                {{ cls.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="form.errors.classroom_id" />
                                </div>
                            </template>

                            <!-- Guru: Mengajar Section -->
                            <template v-if="form.role === 'guru'">
                                <Separator />
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium">Penugasan Mengajar</p>
                                    <Button type="button" variant="outline" size="sm" @click="addTeaching">
                                        <Plus class="mr-1 size-4" />
                                        Tambah
                                    </Button>
                                </div>

                                <!-- Filter helpers for guru -->
                                <div v-if="form.teachings.length > 0" class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <div class="space-y-1">
                                        <Label class="text-xs text-muted-foreground">Filter Tahun Ajaran</Label>
                                        <Select v-model="guruSelectedAcademicYearId">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Semua" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="ay in academicYears"
                                                    :key="ay.id"
                                                    :value="ay.id"
                                                >
                                                    {{ ay.name }} ({{ ay.semester }})
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="space-y-1">
                                        <Label class="text-xs text-muted-foreground">Filter Jurusan</Label>
                                        <Select v-model="guruSelectedDepartmentId">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Semua" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="dept in departments"
                                                    :key="dept.id"
                                                    :value="dept.id"
                                                >
                                                    {{ dept.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div
                                    v-for="(teaching, index) in form.teachings"
                                    :key="index"
                                    class="flex items-end gap-2 rounded-md border p-3"
                                >
                                    <div class="flex-1 space-y-1">
                                        <Label class="text-xs">Kelas</Label>
                                        <Select v-model="teaching.classroom_id">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih kelas" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="cls in filteredClassroomsForGuru"
                                                    :key="cls.id"
                                                    :value="cls.id"
                                                >
                                                    {{ cls.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="flex-1 space-y-1">
                                        <Label class="text-xs">Mata Pelajaran</Label>
                                        <Select v-model="teaching.subject_id">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih mapel" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="subj in filteredSubjectsForGuru"
                                                    :key="subj.id"
                                                    :value="subj.id"
                                                >
                                                    {{ subj.name }} ({{ subj.code }})
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        @click="removeTeaching(index)"
                                    >
                                        <Trash2 class="size-4 text-destructive" />
                                    </Button>
                                </div>
                                <InputError :message="form.errors.teachings" />

                                <p v-if="form.teachings.length === 0" class="text-xs text-muted-foreground">
                                    Belum ada penugasan. Klik "Tambah" untuk menambahkan kelas dan mata pelajaran.
                                </p>
                            </template>

                            <div class="flex items-center justify-end gap-3 pt-4">
                                <Button variant="outline" as-child>
                                    <Link href="/admin/users">Batal</Link>
                                </Button>
                                <LoadingButton :loading="form.processing" type="submit">
                                    Simpan
                                </LoadingButton>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Form Import Excel -->
                <Card>
                    <CardContent class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Import Siswa dari Excel</h3>
                        <div class="space-y-4">
                            <p class="text-sm text-muted-foreground">
                                Upload file Excel (.xlsx, .xls) atau CSV dengan kolom:
                                <strong>nis</strong>, <strong>nama</strong>, <strong>email</strong> (opsional),
                                <strong>password</strong> (opsional), <strong>jurusan</strong> (kode jurusan, opsional),
                                <strong>kelas</strong> (nama kelas, opsional).
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Jika kolom <strong>jurusan</strong> dan <strong>kelas</strong> diisi, siswa akan otomatis ditempatkan
                                ke kelas yang sesuai pada tahun ajaran aktif.
                            </p>

                            <Separator />

                            <form @submit.prevent="submitImport" class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="import-file" class="font-semibold text-sm">File Excel/CSV <span class="text-destructive">*</span></Label>
                                    <Input
                                        id="import-file"
                                        type="file"
                                        accept=".xlsx,.xls,.csv"
                                        class="h-11"
                                        @change="handleFileChange"
                                    />
                                    <InputError :message="importForm.errors.file" />
                                </div>

                                <div class="flex items-center justify-end pt-4">
                                    <LoadingButton :loading="importForm.processing" :disabled="!importForm.file" type="submit">
                                        Import
                                    </LoadingButton>
                                </div>
                            </form>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
