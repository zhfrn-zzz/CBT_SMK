<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { AlertCircle, Lock, User } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
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

const page = usePage();
const appSettings = computed(() => (page.props as any).app_settings ?? {});
const schoolName = computed(() => appSettings.value.school_name || appSettings.value.app_name || 'SMK LMS');
const logoUrl = computed(() => {
    const path = appSettings.value.logo_path;
    if (!path) return null;
    if (path.startsWith('http')) return path;
    if (path.startsWith('images/')) return `/${path}`;
    return `/storage/${path}`;
});
</script>

<template>
    <Head title="Login" />

    <div class="flex min-h-screen items-center justify-center bg-background p-4">
        <div class="w-full max-w-md space-y-6">
            <!-- Logo & School Name -->
            <div class="flex flex-col items-center">
                <img
                    v-if="logoUrl"
                    :src="logoUrl"
                    :alt="schoolName"
                    class="mb-3 h-16 w-16 object-contain"
                    @error="($event.target as HTMLImageElement).style.display = 'none'"
                />
                <AppLogoIcon v-else class="mb-3 h-16 w-16 fill-current text-foreground" />
                <h1 class="text-lg font-semibold text-foreground">{{ schoolName }}</h1>
            </div>

            <!-- Login Card -->
            <div class="rounded-lg border border-border bg-card p-6">
                <div class="mb-5 space-y-1 text-center">
                    <h2 class="text-xl font-semibold text-foreground">Masuk ke Akun</h2>
                    <p class="text-sm text-muted-foreground">Masukkan kredensial Anda untuk melanjutkan</p>
                </div>

                <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
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
                        class="flex items-start gap-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/50 dark:text-red-400"
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
                            class="h-11 w-full"
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
