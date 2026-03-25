<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Card, CardContent } from '@/components/ui/card';
import { BarChart3 } from 'lucide-vue-next';
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
            <PageHeader :title="`Detail Kelas: ${classroom.name}`" description="Data tren nilai per bulan" :icon="BarChart3" />

            <Card>
                <CardContent class="p-6">
                    <p class="text-sm text-muted-foreground">Data tren nilai akan ditampilkan di sini.</p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
