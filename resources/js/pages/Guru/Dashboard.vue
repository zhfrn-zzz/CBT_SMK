<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard Guru',
        href: '/guru/dashboard',
    },
];
</script>

<template>
    <Head title="Dashboard Guru" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h2 class="text-xl font-semibold">Dashboard Guru</h2>
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <h3 class="text-sm font-medium text-muted-foreground">Kelas Diampu</h3>
                    <p class="mt-2 text-3xl font-bold">-</p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <h3 class="text-sm font-medium text-muted-foreground">Ujian Mendatang</h3>
                    <p class="mt-2 text-3xl font-bold">-</p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <h3 class="text-sm font-medium text-muted-foreground">Perlu Dinilai</h3>
                    <p class="mt-2 text-3xl font-bold">-</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
