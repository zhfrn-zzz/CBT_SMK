<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import LoadingButton from '@/Components/LoadingButton.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';
import { Separator } from '@/components/ui/separator';
import { UserCog, KeyRound, Copy, Check } from 'lucide-vue-next';
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

// Reset password
const showResetModal = ref(false);
const resetResult = ref<{ name: string; username: string; password: string } | null>(null);
const resetLoading = ref(false);
const resetCopied = ref(false);
const showConfirmReset = ref(false);

function doResetPassword() {
    resetLoading.value = true;
    showConfirmReset.value = false;

    fetch(`/admin/users/${props.user.id}/reset-password`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
        .then(res => res.json())
        .then(data => {
            resetResult.value = data;
            showResetModal.value = true;
        })
        .finally(() => { resetLoading.value = false; });
}

function copyResetPassword() {
    if (resetResult.value) {
        navigator.clipboard.writeText(resetResult.value.password);
        resetCopied.value = true;
        setTimeout(() => { resetCopied.value = false; }, 2000);
    }
}
</script>

<template>
    <Head title="Edit Pengguna" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-lg p-4">
            <FlashMessage />

            <PageHeader title="Edit Pengguna" :icon="UserCog" />

            <Card class="max-w-2xl">
                <CardContent class="p-6">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name" class="font-semibold text-sm">Nama <span class="text-destructive">*</span></Label>
                            <Input id="name" v-model="form.name" class="h-11" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="username" class="font-semibold text-sm">Username (NIS/NIP) <span class="text-destructive">*</span></Label>
                            <Input id="username" v-model="form.username" class="h-11" />
                            <InputError :message="form.errors.username" />
                        </div>

                        <div class="space-y-2">
                            <Label for="email" class="font-semibold text-sm">Email (opsional)</Label>
                            <Input id="email" v-model="form.email" type="email" class="h-11" />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password" class="font-semibold text-sm">Password (kosongkan jika tidak diubah)</Label>
                            <Input id="password" v-model="form.password" type="password" class="h-11" />
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

                        <div class="flex items-center justify-end gap-3 pt-4">
                            <Button
                                v-if="props.user.role === 'siswa'"
                                type="button"
                                variant="destructive"
                                @click="showConfirmReset = true"
                                :disabled="resetLoading"
                            >
                                <KeyRound class="mr-2 size-4" />
                                Reset Password
                            </Button>
                            <div class="flex-1" />
                            <Button variant="outline" as-child>
                                <Link href="/admin/users">Batal</Link>
                            </Button>
                            <LoadingButton :loading="form.processing" type="submit">
                                Perbarui
                            </LoadingButton>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Reset Password Confirm -->
        <ConfirmDialog
            :open="showConfirmReset"
            :description="`Reset password untuk ${props.user.name}? Password baru akan di-generate otomatis.`"
            @confirm="doResetPassword"
            @cancel="showConfirmReset = false"
        />

        <!-- Reset Password Result Modal -->
        <Dialog v-model:open="showResetModal">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Password Berhasil Direset</DialogTitle>
                    <DialogDescription>Catat password baru berikut. Hanya ditampilkan sekali.</DialogDescription>
                </DialogHeader>
                <div v-if="resetResult" class="space-y-3">
                    <div class="rounded-lg border p-4 space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground">Nama</span>
                            <span class="text-sm font-medium">{{ resetResult.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground">NIS</span>
                            <span class="text-sm font-medium">{{ resetResult.username }}</span>
                        </div>
                        <Separator />
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">Password Baru</span>
                            <span class="text-lg font-bold font-mono select-all">{{ resetResult.password }}</span>
                        </div>
                    </div>
                    <Alert variant="destructive">
                        <AlertDescription>Password ini hanya ditampilkan sekali. Pastikan sudah dicatat.</AlertDescription>
                    </Alert>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="copyResetPassword">
                        <component :is="resetCopied ? Check : Copy" class="mr-2 size-4" />
                        {{ resetCopied ? 'Tersalin!' : 'Salin Password' }}
                    </Button>
                    <Button @click="showResetModal = false">Tutup</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
