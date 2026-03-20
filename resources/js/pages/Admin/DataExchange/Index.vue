<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { Download, Upload, FileText } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import { ref } from 'vue';

const props = defineProps<{
    classrooms: Array<{ id: number; name: string }>;
    academicYears: Array<{ id: number; name: string }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Data Exchange', href: '/admin/data-exchange/export-students' },
];

const exportForm = useForm({
    classroom_id: '',
    academic_year_id: '',
});

const raporForm = useForm({
    classroom_id: '',
    academic_year_id: '',
});

const importForm = useForm({
    file: null as File | null,
});

const fileInput = ref<HTMLInputElement | null>(null);

function submitExport() {
    const params = new URLSearchParams();
    if (exportForm.classroom_id && exportForm.classroom_id !== 'all') params.append('classroom_id', exportForm.classroom_id);
    if (exportForm.academic_year_id && exportForm.academic_year_id !== 'all') params.append('academic_year_id', exportForm.academic_year_id);
    window.location.href = `/admin/data-exchange/export-students/download?${params.toString()}`;
}

function submitRapor() {
    raporForm.post('/admin/analytics/export-rapor');
}

function submitImport() {
    if (!fileInput.value?.files?.[0]) return;
    importForm.file = fileInput.value.files[0];
    importForm.post('/admin/data-exchange/import');
}
</script>

<template>
    <Head title="Data Exchange" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <FlashMessage />
            <h1 class="text-2xl font-bold">Data Exchange</h1>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Export Data Siswa -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Download class="h-5 w-5" />
                            Export Data Siswa
                        </CardTitle>
                        <CardDescription>Export daftar siswa ke format Excel</CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <div>
                            <Label>Kelas (Opsional)</Label>
                            <Select v-model="exportForm.classroom_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Semua Kelas" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Kelas</SelectItem>
                                    <SelectItem v-for="c in classrooms" :key="c.id" :value="c.id.toString()">
                                        {{ c.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>Tahun Ajaran (Opsional)</Label>
                            <Select v-model="exportForm.academic_year_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Semua Tahun Ajaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Tahun Ajaran</SelectItem>
                                    <SelectItem v-for="ay in academicYears" :key="ay.id" :value="ay.id.toString()">
                                        {{ ay.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Button @click="submitExport" class="w-full">
                            <Download class="mr-2 h-4 w-4" />
                            Download Excel
                        </Button>
                    </CardContent>
                </Card>

                <!-- Export Nilai Rapor -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <FileText class="h-5 w-5" />
                            Export Nilai Rapor
                        </CardTitle>
                        <CardDescription>Export rekap nilai per kelas (diproses di background)</CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <div>
                            <Label>Kelas</Label>
                            <Select v-model="raporForm.classroom_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih Kelas" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="c in classrooms" :key="c.id" :value="c.id.toString()">
                                        {{ c.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>Tahun Ajaran</Label>
                            <Select v-model="raporForm.academic_year_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih Tahun Ajaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="ay in academicYears" :key="ay.id" :value="ay.id.toString()">
                                        {{ ay.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Button
                            @click="submitRapor"
                            :disabled="raporForm.processing || !raporForm.classroom_id || !raporForm.academic_year_id"
                            class="w-full"
                        >
                            <FileText class="mr-2 h-4 w-4" />
                            Generate Rapor
                        </Button>
                    </CardContent>
                </Card>

                <!-- Import Siswa -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Upload class="h-5 w-5" />
                            Import Data Siswa
                        </CardTitle>
                        <CardDescription>Import data siswa dari file Excel/CSV</CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <div>
                            <a href="/admin/data-exchange/template" class="text-sm text-primary underline">
                                Download Template Import
                            </a>
                        </div>
                        <div>
                            <Label>File Excel/CSV</Label>
                            <input
                                ref="fileInput"
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-medium file:bg-primary file:text-primary-foreground hover:file:cursor-pointer"
                            />
                        </div>
                        <Button @click="submitImport" :disabled="importForm.processing" class="w-full">
                            <Upload class="mr-2 h-4 w-4" />
                            {{ importForm.processing ? 'Mengimport...' : 'Import Siswa' }}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
