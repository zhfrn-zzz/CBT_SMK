<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { ChevronLeft } from 'lucide-vue-next';
import type { Announcement, BreadcrumbItem } from '@/types';

const props = defineProps<{
    announcement: Announcement;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pengumuman', href: '/siswa/pengumuman' },
    { title: props.announcement.title, href: `/siswa/pengumuman/${props.announcement.id}` },
];

function formatDate(d: string) {
    return new Date(d).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}
</script>

<template>
    <Head :title="announcement.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl p-4 space-y-4">
            <Button variant="ghost" size="sm" as-child>
                <Link href="/siswa/pengumuman"><ChevronLeft class="size-4" />Kembali</Link>
            </Button>

            <div class="space-y-3">
                <h2 class="text-2xl font-semibold">{{ announcement.title }}</h2>
                <p class="text-sm text-muted-foreground">
                    Oleh <strong>{{ announcement.user?.name }}</strong> · {{ formatDate(announcement.published_at) }}
                    <span v-if="announcement.classroom"> · {{ announcement.classroom.name }}</span>
                </p>
            </div>

            <div class="prose prose-sm max-w-none rounded-lg border p-4">
                <!-- eslint-disable-next-line vue/no-v-html -->
                <div v-html="announcement.content" />
            </div>
        </div>
    </AppLayout>
</template>
