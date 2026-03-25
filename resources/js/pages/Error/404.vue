<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
</script>

<template>
    <Head title="404 — Halaman Tidak Ditemukan" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-background px-4 text-center">
        <p class="text-8xl font-extrabold text-primary/20">404</p>
        <h1 class="mt-4 text-2xl font-bold text-foreground">Halaman Tidak Ditemukan</h1>
        <p class="mt-2 max-w-md text-base text-muted-foreground">
            Maaf, halaman yang Anda cari tidak ada atau telah dipindahkan.
        </p>
        <Button as-child class="mt-8 h-11">
            <Link href="/">Kembali ke Beranda</Link>
        </Button>
        <img
            src="/images/logo-smk-bm.png"
            alt="Logo"
            class="mt-12 h-10 w-10 opacity-30"
            @error="($event.target as HTMLImageElement).style.display = 'none'"
        />
    </div>
</template>
