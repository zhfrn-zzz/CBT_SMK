<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { getInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
};

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Pengaturan profil',
        href: edit(),
    },
];

const page = usePage();
const user = computed(() => page.props.auth.user);

// Photo upload
const photoInput = ref<HTMLInputElement | null>(null);
const photoPreview = ref<string | null>(null);
const uploadingPhoto = ref(false);
const photoErrors = ref<string | null>(null);

function selectPhoto(): void {
    photoInput.value?.click();
}

function handlePhotoChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    photoErrors.value = null;

    // Preview
    const reader = new FileReader();
    reader.onload = (e) => {
        photoPreview.value = e.target?.result as string;
    };
    reader.readAsDataURL(file);

    // Upload
    const formData = new FormData();
    formData.append('photo', file);

    uploadingPhoto.value = true;
    router.post('/settings/profile/photo', formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            if (photoInput.value) photoInput.value.value = '';
        },
        onError: (errors) => {
            photoErrors.value = errors.photo ?? null;
            photoPreview.value = null;
        },
        onFinish: () => {
            uploadingPhoto.value = false;
        },
    });
}

function deletePhoto(): void {
    router.delete('/settings/profile/photo', {
        preserveScroll: true,
    });
}

const currentAvatar = computed(() => {
    if (photoPreview.value) return photoPreview.value;
    return user.value?.avatar ?? user.value?.photo_url ?? null;
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Pengaturan profil" />

        <h1 class="sr-only">Pengaturan profil</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <!-- Photo Upload Section -->
                <Heading
                    variant="small"
                    title="Foto profil"
                    description="Unggah foto profil Anda (JPG, PNG, WebP, maks 2MB)"
                />

                <div class="flex items-center gap-4">
                    <Avatar class="h-20 w-20">
                        <AvatarImage
                            v-if="currentAvatar"
                            :src="currentAvatar"
                            :alt="user.name"
                        />
                        <AvatarFallback class="text-xl">
                            {{ getInitials(user.name) }}
                        </AvatarFallback>
                    </Avatar>

                    <div class="flex flex-col gap-2">
                        <div class="flex gap-2">
                            <input
                                ref="photoInput"
                                type="file"
                                class="hidden"
                                accept="image/jpeg,image/png,image/webp"
                                @change="handlePhotoChange"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="uploadingPhoto"
                                @click="selectPhoto"
                            >
                                {{ uploadingPhoto ? 'Mengunggah...' : 'Ganti Foto' }}
                            </Button>
                            <Button
                                v-if="user.avatar || user.photo_url"
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="deletePhoto"
                            >
                                Hapus
                            </Button>
                        </div>
                        <InputError v-if="photoErrors" :message="photoErrors" />
                    </div>
                </div>

                <hr class="border-border" />

                <!-- Profile Information -->
                <Heading
                    variant="small"
                    title="Informasi profil"
                    description="Perbarui nama, email, telepon, dan bio Anda"
                />

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="name">Nama</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            name="name"
                            :default-value="user.name"
                            required
                            autocomplete="name"
                            placeholder="Nama lengkap"
                        />
                        <InputError class="mt-2" :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Alamat email</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="user.email ?? ''"
                            required
                            autocomplete="username"
                            placeholder="Alamat email"
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone">Nomor telepon</Label>
                        <Input
                            id="phone"
                            type="tel"
                            class="mt-1 block w-full"
                            name="phone"
                            :default-value="(user as Record<string, unknown>).phone as string ?? ''"
                            autocomplete="tel"
                            placeholder="08xxxxxxxxxx"
                            maxlength="20"
                        />
                        <InputError class="mt-2" :message="errors.phone" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="bio">Bio</Label>
                        <Textarea
                            id="bio"
                            class="mt-1 block w-full"
                            name="bio"
                            :default-value="(user as Record<string, unknown>).bio as string ?? ''"
                            placeholder="Ceritakan sedikit tentang diri Anda..."
                            rows="3"
                            maxlength="500"
                        />
                        <InputError class="mt-2" :message="errors.bio" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Alamat email Anda belum diverifikasi.
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Klik di sini untuk mengirim ulang email verifikasi.
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            Link verifikasi baru telah dikirim ke alamat email Anda.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-profile-button"
                            >Simpan</Button
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

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
