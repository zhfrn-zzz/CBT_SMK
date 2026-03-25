<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

function reload() {
    window.location.reload();
}
</script>

<template>
    <Head title="500 — Terjadi Kesalahan" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-background px-4 text-center">
        <p class="text-8xl font-extrabold text-primary/20">500</p>
        <h1 class="mt-4 text-2xl font-bold text-foreground">Terjadi Kesalahan</h1>
        <p class="mt-2 max-w-md text-base text-muted-foreground">
            Maaf, terjadi masalah pada server. Tim kami telah diberitahu. Silakan coba lagi nanti.
        </p>
        <div class="mt-8 flex items-center gap-3">
            <Button class="h-11" @click="reload">
                Muat Ulang Halaman
            </Button>
            <Button as-child variant="outline" class="h-11">
                <Link href="/">Kembali ke Beranda</Link>
            </Button>
        </div>
        <img
            src="/images/logo-smk-bm.png"
            alt="Logo"
            class="mt-12 h-10 w-10 opacity-30"
            @error="($event.target as HTMLImageElement).style.display = 'none'"
        />
    </div>
</template>
