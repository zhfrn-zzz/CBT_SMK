<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { AlertCircle, Lock, User } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Login" />

    <div class="flex min-h-screen">
        <!-- Left Branding Panel -->
        <div class="relative hidden overflow-hidden bg-gradient-to-br from-primary to-blue-800 lg:flex lg:w-[55%] lg:flex-col lg:items-center lg:justify-center">
            <!-- Decorative circles -->
            <div class="absolute -left-20 -top-20 h-72 w-72 rounded-full border border-white/10" />
            <div class="absolute -bottom-32 -right-16 h-96 w-96 rounded-full border border-white/10" />
            <div class="absolute left-1/3 top-1/4 h-48 w-48 rounded-full border border-white/10" />

            <div class="relative z-10 flex flex-col items-center px-12 text-center">
                <img
                    src="/images/logo-smk-bm.png"
                    alt="Logo SMK Bina Mandiri"
                    class="mb-8 h-28 w-28 rounded-full bg-white/10 object-contain p-3"
                    @error="($event.target as HTMLImageElement).style.display = 'none'"
                />
                <h1 class="text-3xl font-bold text-white">SMK Bina Mandiri</h1>
                <h2 class="mt-1 text-xl font-medium text-white/90">Kota Bekasi</h2>
                <p class="mt-4 text-blue-100">Sistem Pembelajaran & Ujian Digital</p>
            </div>
        </div>

        <!-- Right Form Panel -->
        <div class="flex w-full items-center justify-center bg-background p-8 lg:w-[45%]">
            <div class="w-full max-w-sm space-y-6">
                <!-- Mobile logo -->
                <div class="flex flex-col items-center lg:hidden">
                    <img
                        src="/images/logo-smk-bm.png"
                        alt="Logo SMK Bina Mandiri"
                        class="mb-4 h-16 w-16 object-contain"
                        @error="($event.target as HTMLImageElement).style.display = 'none'"
                    />
                </div>

                <div class="space-y-2 text-center lg:text-left">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">Masuk ke Akun</h1>
                    <p class="text-sm text-muted-foreground">Masukkan kredensial Anda untuk melanjutkan</p>
                </div>

                <div v-if="status" class="text-center text-sm font-medium text-green-600">
                    {{ status }}
                </div>

                <Form
                    v-bind="store.form()"
                    :reset-on-success="['password']"
                    v-slot="{ errors, processing }"
                    class="space-y-5"
                >
                    <!-- Error alert -->
                    <div
                        v-if="errors.username || errors.password"
                        class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/50 dark:text-red-400"
                    >
                        <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>Username atau password yang Anda masukkan salah. Silakan coba lagi.</span>
                    </div>

                    <div class="space-y-4">
                        <!-- Username -->
                        <div class="space-y-2">
                            <Label for="username">NIP / NIS / Username</Label>
                            <div class="relative">
                                <User class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="username"
                                    type="text"
                                    name="username"
                                    required
                                    autofocus
                                    :tabindex="1"
                                    autocomplete="username"
                                    placeholder="Masukkan username Anda"
                                    class="h-11 pl-10"
                                />
                            </div>
                            <InputError :message="errors.username" />
                        </div>

                        <!-- Password -->
                        <div class="space-y-2">
                            <Label for="password">Kata Sandi</Label>
                            <div class="relative">
                                <Lock class="pointer-events-none absolute left-3 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    :tabindex="2"
                                    autocomplete="current-password"
                                    placeholder="Masukkan kata sandi"
                                    class="h-11 pl-10"
                                />
                            </div>
                            <InputError :message="errors.password" />
                        </div>

                        <!-- Remember me -->
                        <div class="flex items-center">
                            <Label for="remember" class="flex items-center space-x-3">
                                <Checkbox id="remember" name="remember" :tabindex="3" />
                                <span>Ingat saya</span>
                            </Label>
                        </div>

                        <!-- Submit -->
                        <Button
                            type="submit"
                            class="h-12 w-full text-base"
                            :tabindex="4"
                            :disabled="processing"
                            data-test="login-button"
                        >
                            <Spinner v-if="processing" />
                            Masuk
                        </Button>
                    </div>

                    <!-- Forgot password -->
                    <div v-if="canResetPassword" class="text-center">
                        <TextLink :href="request()" class="text-sm" :tabindex="5">
                            Lupa password?
                        </TextLink>
                    </div>
                </Form>
            </div>
        </div>
    </div>
</template>
