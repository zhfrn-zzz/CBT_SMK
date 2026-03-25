<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import LoadingButton from '@/Components/LoadingButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import InputError from '@/components/InputError.vue';
import { KeyRound, ShieldCheck } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    examSession: {
        id: number;
        name: string;
        subject: string;
        duration_minutes: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/siswa/dashboard' },
    { title: 'Ujian', href: '/siswa/ujian' },
    { title: 'Verifikasi Token', href: '#' },
];

const token = ref('');
const errors = ref<Record<string, string>>({});
const processing = ref(false);

function submit() {
    processing.value = true;
    router.post(`/siswa/ujian/${props.examSession.id}/verify-token`, {
        token: token.value,
    }, {
        onError: (e) => { errors.value = e; },
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <Head title="Verifikasi Token Ujian" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <PageHeader title="Verifikasi Token" :icon="ShieldCheck" />

            <div class="flex flex-1 items-center justify-center">
            <Card class="w-full max-w-md">
                <CardHeader class="text-center">
                    <div class="mx-auto mb-2 flex size-12 items-center justify-center rounded-full bg-primary/10">
                        <KeyRound class="size-6 text-primary" />
                    </div>
                    <CardTitle class="text-xl">{{ examSession.name }}</CardTitle>
                    <CardDescription>
                        {{ examSession.subject }} &bull; {{ examSession.duration_minutes }} menit
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="space-y-2">
                            <Label for="token">Masukkan Token Ujian</Label>
                            <Input
                                id="token"
                                v-model="token"
                                placeholder="Contoh: AB12CD"
                                class="text-center text-lg font-mono tracking-widest uppercase"
                                maxlength="10"
                                autofocus
                            />
                            <InputError :message="errors.token" />
                        </div>
                        <LoadingButton type="submit" class="w-full" :loading="processing" :disabled="!token">Mulai Ujian</LoadingButton>
                    </form>
                </CardContent>
            </Card>
            </div>
        </div>
    </AppLayout>
</template>
