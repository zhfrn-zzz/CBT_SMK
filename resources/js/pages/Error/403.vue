<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
</script>

<template>
    <Head title="403 — Akses Ditolak" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-background px-4 text-center">
        <p class="text-8xl font-extrabold text-primary/20">403</p>
        <h1 class="mt-4 text-2xl font-bold text-foreground">Akses Ditolak</h1>
        <p class="mt-2 max-w-md text-base text-muted-foreground">
            Anda tidak memiliki izin untuk mengakses halaman ini. Hubungi administrator jika ini adalah kesalahan.
        </p>
        <Button as-child class="mt-8 h-11">
            <Link href="/dashboard">Kembali ke Dashboard</Link>
        </Button>
        <img
            src="/images/logo-smk-bm.png"
            alt="Logo"
            class="mt-12 h-10 w-10 opacity-30"
            @error="($event.target as HTMLImageElement).style.display = 'none'"
        />
    </div>
</template>
