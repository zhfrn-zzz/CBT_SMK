<script setup lang="ts">
import { Form, Head, useForm } from '@inertiajs/vue3';
import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/user-password';
import type { BreadcrumbItem } from '@/types';
import { ref } from 'vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Pengaturan password',
        href: edit(),
    },
];

const logoutForm = useForm({
    password: '',
});

const logoutSuccess = ref(false);

function logoutOtherDevices() {
    logoutForm.post('/settings/logout-other-devices', {
        preserveScroll: true,
        onSuccess: () => {
            logoutForm.reset();
            logoutSuccess.value = true;
            setTimeout(() => (logoutSuccess.value = false), 3000);
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Pengaturan password" />

        <h1 class="sr-only">Pengaturan password</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Perbarui password"
                    description="Pastikan akun Anda menggunakan password yang panjang dan acak agar tetap aman"
                />

                <Form
                    v-bind="PasswordController.update.form()"
                    :options="{
                        preserveScroll: true,
                    }"
                    reset-on-success
                    :reset-on-error="[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="current_password">Password saat ini</Label>
                        <PasswordInput
                            id="current_password"
                            name="current_password"
                            class="mt-1 block w-full"
                            autocomplete="current-password"
                            placeholder="Password saat ini"
                        />
                        <InputError :message="errors.current_password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">Password baru</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            placeholder="Password baru"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation"
                            >Konfirmasi password</Label
                        >
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            placeholder="Konfirmasi password"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-password-button"
                            >Simpan password</Button
                        >

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Tersimpan.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>

            <div class="border-t pt-6 mt-6 space-y-6">
                <Heading
                    variant="small"
                    title="Keluarkan sesi perangkat lain"
                    description="Jika Anda merasa akun Anda diakses dari perangkat lain, Anda dapat mengeluarkan semua sesi di perangkat lain."
                />

                <form @submit.prevent="logoutOtherDevices" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="logout_password">Password</Label>
                        <PasswordInput
                            id="logout_password"
                            v-model="logoutForm.password"
                            class="mt-1 block w-full"
                            autocomplete="current-password"
                            placeholder="Masukkan password Anda"
                        />
                        <InputError :message="logoutForm.errors.password" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="logoutForm.processing"
                        >
                            Keluarkan sesi lain
                        </Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="logoutSuccess"
                                class="text-sm text-neutral-600"
                            >
                                Semua sesi di perangkat lain telah dikeluarkan.
                            </p>
                        </Transition>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
