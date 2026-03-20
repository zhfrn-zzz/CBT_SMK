<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    classroom: { id: number; name: string };
    academicYears: Array<{ id: number; name: string }>;
    trend: unknown[];
    filters: { academic_year_id: number | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Analitik', href: '/admin/analytics' },
    { title: props.classroom.name, href: '#' },
];
</script>

<template>
    <Head :title="`Detail Kelas - ${classroom.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <h1 class="text-2xl font-bold">Detail Kelas: {{ classroom.name }}</h1>
            <p class="text-muted-foreground">Data tren nilai per bulan</p>
        </div>
    </AppLayout>
</template>
