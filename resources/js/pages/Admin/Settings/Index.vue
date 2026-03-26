<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import LoadingButton from '@/Components/LoadingButton.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Settings } from 'lucide-vue-next';
import type { BreadcrumbItem, SettingsPageProps } from '@/types';

const props = defineProps<SettingsPageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Pengaturan', href: '/admin/settings' },
];

const activeTab = ref('general');

// ── General Tab ──────────────────────────────────────────────────────
const generalForm = useForm({
    app_name: (props.settings.general.app_name as string) ?? '',
    school_name: (props.settings.general.school_name as string) ?? '',
    school_address: (props.settings.general.school_address as string) ?? '',
    school_phone: (props.settings.general.school_phone as string) ?? '',
    school_email: (props.settings.general.school_email as string) ?? '',
    school_website: (props.settings.general.school_website as string) ?? '',
    school_tagline: (props.settings.general.school_tagline as string) ?? '',
});

function submitGeneral() {
    generalForm.put('/admin/settings/general', { preserveScroll: true });
}

// ── Appearance Tab ───────────────────────────────────────────────────
const appearanceForm = useForm({
    logo: null as File | null,
    logo_small: null as File | null,
    primary_color: (props.settings.appearance.primary_color as string) ?? '#2563eb',
    secondary_color: (props.settings.appearance.secondary_color as string) ?? '#64748b',
    login_bg_type: (props.settings.appearance.login_bg_type as string) ?? 'color',
    login_bg_value: (props.settings.appearance.login_bg_value as string) ?? '#f8fafc',
    footer_text: (props.settings.appearance.footer_text as string) ?? '',
    show_powered_by: Boolean(props.settings.appearance.show_powered_by),
});

const logoPreview = ref<string | null>(null);
const logoSmallPreview = ref<string | null>(null);

function handleLogoChange(e: Event) {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        appearanceForm.logo = file;
        logoPreview.value = URL.createObjectURL(file);
    }
}

function handleLogoSmallChange(e: Event) {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        appearanceForm.logo_small = file;
        logoSmallPreview.value = URL.createObjectURL(file);
    }
}

function submitAppearance() {
    const data = appearanceForm.data();

    router.post('/admin/settings/appearance', {
        _method: 'PUT',
        ...data,
        // Explicitly convert boolean so FormData sends '1'/'0' reliably
        show_powered_by: data.show_powered_by ? '1' : '0',
    }, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            logoPreview.value = null;
            logoSmallPreview.value = null;
        },
    });
}

// ── Exam Tab ─────────────────────────────────────────────────────────
const examForm = useForm({
    default_duration_minutes: Number(props.settings.exam.default_duration_minutes) || 60,
    auto_submit_on_timeout: Boolean(props.settings.exam.auto_submit_on_timeout),
    show_result_after_submit: Boolean(props.settings.exam.show_result_after_submit),
    anti_cheat_enabled: Boolean(props.settings.exam.anti_cheat_enabled),
    max_tab_switches_default: Number(props.settings.exam.max_tab_switches_default) || 3,
    allow_mobile_exam: Boolean(props.settings.exam.allow_mobile_exam),
    device_lock_default: Boolean(props.settings.exam.device_lock_default),
    watermark_enabled: Boolean(props.settings.exam.watermark_enabled),
});

function submitExam() {
    examForm.put('/admin/settings/exam', { preserveScroll: true });
}

// ── Email Tab ────────────────────────────────────────────────────────
const emailForm = useForm({
    smtp_host: (props.settings.email.smtp_host as string) ?? '',
    smtp_port: Number(props.settings.email.smtp_port) || 587,
    smtp_username: (props.settings.email.smtp_username as string) ?? '',
    smtp_password: (props.settings.email.smtp_password as string) ?? '',
    smtp_encryption: (props.settings.email.smtp_encryption as string) ?? 'tls',
    smtp_from_address: (props.settings.email.smtp_from_address as string) ?? '',
    smtp_from_name: (props.settings.email.smtp_from_name as string) ?? '',
});

function submitEmail() {
    emailForm.put('/admin/settings/email', { preserveScroll: true });
}

const testEmailLoading = ref(false);
const testEmailResult = ref<{ success: boolean; message: string } | null>(null);

function sendTestEmail() {
    testEmailLoading.value = true;
    testEmailResult.value = null;
    router.post('/admin/settings/email/test', {}, {
        preserveScroll: true,
        onSuccess: () => {
            testEmailResult.value = { success: true, message: 'Email test berhasil dikirim! Cek inbox Anda.' };
            testEmailLoading.value = false;
        },
        onError: (errors: Record<string, string>) => {
            testEmailResult.value = { success: false, message: errors.email ?? 'Gagal mengirim email test.' };
            testEmailLoading.value = false;
        },
    });
}

function currentLogoUrl(): string | null {
    const path = props.settings.appearance.logo_path as string;
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
}
</script>

<template>
    <Head title="Pengaturan" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <PageHeader title="Pengaturan Sistem" description="Konfigurasi aplikasi dan sekolah" :icon="Settings" />

            <Tabs v-model="activeTab" class="w-full">
                <TabsList class="grid w-full grid-cols-4 max-w-xl">
                    <TabsTrigger value="general">Umum</TabsTrigger>
                    <TabsTrigger value="appearance">Tampilan</TabsTrigger>
                    <TabsTrigger value="exam">Ujian</TabsTrigger>
                    <TabsTrigger value="email">Email</TabsTrigger>
                </TabsList>

                <!-- ── Tab Umum ──────────────────────────────────────── -->
                <TabsContent value="general">
                    <Card class="max-w-2xl">
                        <CardHeader>
                            <CardTitle>Pengaturan Umum</CardTitle>
                            <CardDescription>Informasi dasar aplikasi dan sekolah</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form @submit.prevent="submitGeneral" class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="app_name" class="font-semibold text-sm">Nama Aplikasi <span class="text-destructive">*</span></Label>
                                    <Input id="app_name" v-model="generalForm.app_name" class="h-11" />
                                    <InputError :message="generalForm.errors.app_name" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="school_name" class="font-semibold text-sm">Nama Sekolah <span class="text-destructive">*</span></Label>
                                    <Input id="school_name" v-model="generalForm.school_name" class="h-11" />
                                    <InputError :message="generalForm.errors.school_name" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="school_address" class="font-semibold text-sm">Alamat Sekolah</Label>
                                    <Input id="school_address" v-model="generalForm.school_address" class="h-11" />
                                    <InputError :message="generalForm.errors.school_address" />
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="school_phone" class="font-semibold text-sm">Telepon</Label>
                                        <Input id="school_phone" v-model="generalForm.school_phone" class="h-11" />
                                        <InputError :message="generalForm.errors.school_phone" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="school_email" class="font-semibold text-sm">Email Sekolah</Label>
                                        <Input id="school_email" v-model="generalForm.school_email" type="email" class="h-11" />
                                        <InputError :message="generalForm.errors.school_email" />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="school_website" class="font-semibold text-sm">Website</Label>
                                    <Input id="school_website" v-model="generalForm.school_website" placeholder="https://..." class="h-11" />
                                    <InputError :message="generalForm.errors.school_website" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="school_tagline" class="font-semibold text-sm">Tagline / Motto</Label>
                                    <Input id="school_tagline" v-model="generalForm.school_tagline" class="h-11" />
                                    <InputError :message="generalForm.errors.school_tagline" />
                                </div>

                                <div class="flex justify-end pt-4">
                                    <LoadingButton :loading="generalForm.processing" type="submit">
                                        Simpan Pengaturan Umum
                                    </LoadingButton>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- ── Tab Tampilan ──────────────────────────────────── -->
                <TabsContent value="appearance">
                    <Card class="max-w-2xl">
                        <CardHeader>
                            <CardTitle>Pengaturan Tampilan</CardTitle>
                            <CardDescription>Logo, warna, dan tampilan halaman login</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form @submit.prevent="submitAppearance" class="space-y-6">
                                <div class="space-y-2">
                                    <Label for="logo" class="font-semibold text-sm">Logo Utama</Label>
                                    <div class="flex items-center gap-4">
                                        <img
                                            v-if="logoPreview || currentLogoUrl()"
                                            :src="logoPreview || currentLogoUrl()!"
                                            alt="Logo"
                                            class="h-16 w-16 rounded-lg border object-contain p-1"
                                        />
                                        <div class="flex-1">
                                            <Input id="logo" type="file" accept="image/png,image/jpeg,image/svg+xml" @change="handleLogoChange" />
                                            <p class="text-xs text-muted-foreground mt-1">PNG, JPG, atau SVG. Maksimal 2MB.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="logo_small" class="font-semibold text-sm">Logo Kecil / Favicon</Label>
                                    <div class="flex items-center gap-4">
                                        <img
                                            v-if="logoSmallPreview"
                                            :src="logoSmallPreview"
                                            alt="Logo Kecil"
                                            class="h-10 w-10 rounded-lg border object-contain p-1"
                                        />
                                        <div class="flex-1">
                                            <Input id="logo_small" type="file" accept="image/png,image/jpeg,image/svg+xml,image/x-icon" @change="handleLogoSmallChange" />
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="primary_color" class="font-semibold text-sm">Warna Primer</Label>
                                        <div class="flex items-center gap-2">
                                            <input
                                                id="primary_color"
                                                v-model="appearanceForm.primary_color"
                                                type="color"
                                                class="h-11 w-14 cursor-pointer rounded border p-1"
                                            />
                                            <Input v-model="appearanceForm.primary_color" class="h-11 font-mono" maxlength="7" />
                                        </div>
                                        <InputError :message="appearanceForm.errors.primary_color" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="secondary_color" class="font-semibold text-sm">Warna Sekunder</Label>
                                        <div class="flex items-center gap-2">
                                            <input
                                                id="secondary_color"
                                                v-model="appearanceForm.secondary_color"
                                                type="color"
                                                class="h-11 w-14 cursor-pointer rounded border p-1"
                                            />
                                            <Input v-model="appearanceForm.secondary_color" class="h-11 font-mono" maxlength="7" />
                                        </div>
                                        <InputError :message="appearanceForm.errors.secondary_color" />
                                    </div>
                                </div>

                                <Separator />

                                <div class="space-y-2">
                                    <Label for="footer_text" class="font-semibold text-sm">Teks Footer</Label>
                                    <Input id="footer_text" v-model="appearanceForm.footer_text" placeholder="© 2026 Nama Sekolah" class="h-11" />
                                    <InputError :message="appearanceForm.errors.footer_text" />
                                </div>

                                <div class="flex items-center justify-between rounded-lg border p-4">
                                    <div>
                                        <p class="text-sm font-semibold">Tampilkan "Powered by"</p>
                                        <p class="text-xs text-muted-foreground">Tampilkan credit di footer halaman</p>
                                    </div>
                                    <Switch v-model:checked="appearanceForm.show_powered_by" />
                                </div>

                                <div class="flex justify-end pt-4">
                                    <LoadingButton :loading="appearanceForm.processing" type="submit">
                                        Simpan Pengaturan Tampilan
                                    </LoadingButton>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- ── Tab Ujian ─────────────────────────────────────── -->
                <TabsContent value="exam">
                    <Card class="max-w-2xl">
                        <CardHeader>
                            <CardTitle>Pengaturan Ujian</CardTitle>
                            <CardDescription>Default konfigurasi untuk ujian baru</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form @submit.prevent="submitExam" class="space-y-6">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="default_duration_minutes" class="font-semibold text-sm">Durasi Default (menit)</Label>
                                        <Input id="default_duration_minutes" v-model.number="examForm.default_duration_minutes" type="number" min="1" max="480" class="h-11" />
                                        <InputError :message="examForm.errors.default_duration_minutes" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="max_tab_switches_default" class="font-semibold text-sm">Maks Pergantian Tab</Label>
                                        <Input id="max_tab_switches_default" v-model.number="examForm.max_tab_switches_default" type="number" min="1" max="99" class="h-11" />
                                        <InputError :message="examForm.errors.max_tab_switches_default" />
                                    </div>
                                </div>

                                <Separator />

                                <div class="space-y-3">
                                    <div class="flex items-center justify-between rounded-lg border p-4">
                                        <div>
                                            <p class="text-sm font-semibold">Auto Submit saat Timeout</p>
                                            <p class="text-xs text-muted-foreground">Otomatis kumpulkan jawaban saat waktu habis</p>
                                        </div>
                                        <Switch v-model:checked="examForm.auto_submit_on_timeout" />
                                    </div>

                                    <div class="flex items-center justify-between rounded-lg border p-4">
                                        <div>
                                            <p class="text-sm font-semibold">Tampilkan Hasil Setelah Submit</p>
                                            <p class="text-xs text-muted-foreground">Siswa bisa langsung lihat nilai setelah submit</p>
                                        </div>
                                        <Switch v-model:checked="examForm.show_result_after_submit" />
                                    </div>

                                    <div class="flex items-center justify-between rounded-lg border p-4">
                                        <div>
                                            <p class="text-sm font-semibold">Anti-Cheat</p>
                                            <p class="text-xs text-muted-foreground">Deteksi tab switching dan fullscreen exit</p>
                                        </div>
                                        <Switch v-model:checked="examForm.anti_cheat_enabled" />
                                    </div>

                                    <div class="flex items-center justify-between rounded-lg border p-4">
                                        <div>
                                            <p class="text-sm font-semibold">Izinkan Ujian di Perangkat Mobile</p>
                                            <p class="text-xs text-muted-foreground">Siswa bisa mengerjakan ujian dari HP/tablet</p>
                                        </div>
                                        <Switch v-model:checked="examForm.allow_mobile_exam" />
                                    </div>

                                    <div class="flex items-center justify-between rounded-lg border p-4">
                                        <div>
                                            <p class="text-sm font-semibold">Device Lock Default</p>
                                            <p class="text-xs text-muted-foreground">Kunci ujian ke 1 perangkat per siswa</p>
                                        </div>
                                        <Switch v-model:checked="examForm.device_lock_default" />
                                    </div>

                                    <div class="flex items-center justify-between rounded-lg border p-4">
                                        <div>
                                            <p class="text-sm font-semibold">Watermark Nama Siswa</p>
                                            <p class="text-xs text-muted-foreground">Tampilkan watermark nama siswa di halaman ujian</p>
                                        </div>
                                        <Switch v-model:checked="examForm.watermark_enabled" />
                                    </div>
                                </div>

                                <div class="flex justify-end pt-4">
                                    <LoadingButton :loading="examForm.processing" type="submit">
                                        Simpan Pengaturan Ujian
                                    </LoadingButton>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- ── Tab Email ─────────────────────────────────────── -->
                <TabsContent value="email">
                    <Card class="max-w-2xl">
                        <CardHeader>
                            <CardTitle>Pengaturan Email (SMTP)</CardTitle>
                            <CardDescription>Konfigurasi server email untuk notifikasi</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form @submit.prevent="submitEmail" class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="smtp_host" class="font-semibold text-sm">SMTP Host</Label>
                                    <Input id="smtp_host" v-model="emailForm.smtp_host" placeholder="smtp.gmail.com" class="h-11" />
                                    <InputError :message="emailForm.errors.smtp_host" />
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="smtp_port" class="font-semibold text-sm">Port</Label>
                                        <Input id="smtp_port" v-model.number="emailForm.smtp_port" type="number" class="h-11" />
                                        <InputError :message="emailForm.errors.smtp_port" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="smtp_encryption" class="font-semibold text-sm">Enkripsi</Label>
                                        <Select v-model="emailForm.smtp_encryption">
                                            <SelectTrigger class="h-11">
                                                <SelectValue placeholder="Pilih enkripsi" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">Tidak ada</SelectItem>
                                                <SelectItem value="tls">TLS</SelectItem>
                                                <SelectItem value="ssl">SSL</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="emailForm.errors.smtp_encryption" />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="smtp_username" class="font-semibold text-sm">Username</Label>
                                    <Input id="smtp_username" v-model="emailForm.smtp_username" class="h-11" />
                                    <InputError :message="emailForm.errors.smtp_username" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="smtp_password" class="font-semibold text-sm">Password</Label>
                                    <Input id="smtp_password" v-model="emailForm.smtp_password" type="password" class="h-11" />
                                    <InputError :message="emailForm.errors.smtp_password" />
                                </div>

                                <Separator />

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="smtp_from_address" class="font-semibold text-sm">Email Pengirim</Label>
                                        <Input id="smtp_from_address" v-model="emailForm.smtp_from_address" type="email" placeholder="noreply@sekolah.sch.id" class="h-11" />
                                        <InputError :message="emailForm.errors.smtp_from_address" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="smtp_from_name" class="font-semibold text-sm">Nama Pengirim</Label>
                                        <Input id="smtp_from_name" v-model="emailForm.smtp_from_name" placeholder="SMK Bina Mandiri" class="h-11" />
                                        <InputError :message="emailForm.errors.smtp_from_name" />
                                    </div>
                                </div>

                                <div class="flex justify-end pt-4">
                                    <LoadingButton :loading="emailForm.processing" type="submit">
                                        Simpan Pengaturan Email
                                    </LoadingButton>
                                </div>
                            </form>

                            <Separator class="my-6" />

                            <!-- Test Email -->
                            <div class="space-y-3">
                                <div>
                                    <h4 class="text-sm font-semibold">Kirim Email Test</h4>
                                    <p class="text-muted-foreground text-sm">Kirim email percobaan ke alamat email Anda untuk memastikan konfigurasi SMTP benar.</p>
                                </div>
                                <div v-if="testEmailResult" :class="['rounded-md border p-3 text-sm', testEmailResult.success ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200' : 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200']">
                                    {{ testEmailResult.message }}
                                </div>
                                <Button type="button" variant="outline" :disabled="testEmailLoading" @click="sendTestEmail">
                                    <span v-if="testEmailLoading">Mengirim...</span>
                                    <span v-else>Kirim Email Test</span>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
