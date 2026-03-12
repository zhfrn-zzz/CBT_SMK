<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthBase from '@/layouts/AuthLayout.vue';
</script>

<template>
    <AuthBase
        title="Registrasi Tidak Tersedia"
        description="Pendaftaran akun hanya dilakukan oleh Administrator"
    >
        <Head title="Register" />

        <p class="text-center text-sm text-muted-foreground">
            Silakan hubungi administrator untuk pembuatan akun.
        </p>
    </AuthBase>
</template>
