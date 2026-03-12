<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard Admin',
        href: '/admin/dashboard',
    },
];
</script>

<template>
    <Head title="Dashboard Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h2 class="text-xl font-semibold">Dashboard Administrator</h2>
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <h3 class="text-sm font-medium text-muted-foreground">Total Pengguna</h3>
                    <p class="mt-2 text-3xl font-bold">-</p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <h3 class="text-sm font-medium text-muted-foreground">Ujian Aktif</h3>
                    <p class="mt-2 text-3xl font-bold">-</p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <h3 class="text-sm font-medium text-muted-foreground">Ujian Hari Ini</h3>
                    <p class="mt-2 text-3xl font-bold">-</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
