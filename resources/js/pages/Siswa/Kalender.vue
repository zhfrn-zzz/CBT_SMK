<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import CalendarGrid from '@/components/Calendar/CalendarGrid.vue';
import { Card, CardContent } from '@/components/ui/card';
import { CalendarDays } from 'lucide-vue-next';
import type { BreadcrumbItem, CalendarEvent } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/siswa/dashboard' },
    { title: 'Kalender', href: '/siswa/kalender' },
];

const events = ref<CalendarEvent[]>([]);
const loading = ref(false);

async function fetchEvents(year: number, month: number) {
    loading.value = true;
    try {
        const response = await fetch(`/api/calendar/events?year=${year}&month=${month}`);
        events.value = await response.json();
    } catch {
        events.value = [];
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Kalender Akademik" />

        <div class="mx-auto max-w-5xl space-y-6 p-6">
            <PageHeader title="Kalender" description="Jadwal kegiatan" :icon="CalendarDays" />

            <Card>
                <CardContent class="p-4">
                    <div class="relative">
                        <div v-if="loading" class="absolute inset-0 z-10 flex items-center justify-center bg-background/50">
                            <div class="text-sm text-muted-foreground">Memuat...</div>
                        </div>
                        <CalendarGrid :events="events" @month-change="fetchEvents" />
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
