<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { ChevronLeft, Megaphone } from 'lucide-vue-next';
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

            <PageHeader :title="announcement.title" :icon="Megaphone" />
            <p class="text-sm text-muted-foreground">
                Oleh <strong>{{ announcement.user?.name }}</strong> · {{ formatDate(announcement.published_at) }}
                <span v-if="announcement.classroom"> · {{ announcement.classroom.name }}</span>
            </p>

            <Card>
                <CardContent class="p-6 prose prose-sm max-w-none">
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <div v-html="announcement.content" />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
