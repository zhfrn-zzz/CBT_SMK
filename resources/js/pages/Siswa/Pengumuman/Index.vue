<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Megaphone, Pin } from 'lucide-vue-next';
import type { Announcement, BreadcrumbItem, PaginatedData } from '@/types';

const props = defineProps<{
    announcements: PaginatedData<Announcement>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/siswa/dashboard' },
    { title: 'Pengumuman', href: '/siswa/pengumuman' },
];

function formatDate(d: string) {
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}
</script>

<template>
    <Head title="Pengumuman" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <PageHeader title="Pengumuman" description="Pengumuman terbaru" :icon="Megaphone" />

            <EmptyState v-if="announcements.data.length === 0" :icon="Megaphone" title="Belum ada pengumuman" description="Belum ada pengumuman saat ini." />

            <div class="space-y-2">
                <Link
                    v-for="a in announcements.data" :key="a.id"
                    :href="`/siswa/pengumuman/${a.id}`"
                    class="flex items-start gap-3 rounded-xl border bg-card p-4 shadow-sm hover:bg-slate-50/50 transition-colors"
                    :class="a.is_pinned ? 'border-primary/30 bg-primary/5' : ''"
                >
                    <Pin v-if="a.is_pinned" class="size-4 text-primary mt-0.5 shrink-0" />
                    <div class="flex-1 min-w-0">
                        <p class="font-medium">{{ a.title }}</p>
                        <p class="text-sm text-muted-foreground mt-1 line-clamp-2">{{ a.content }}</p>
                        <p class="text-xs text-muted-foreground mt-2">
                            {{ a.user?.name }} · {{ formatDate(a.published_at) }}
                            <span v-if="a.classroom"> · {{ a.classroom.name }}</span>
                            <span v-else> · Semua Kelas</span>
                        </p>
                    </div>
                </Link>
            </div>

            <Pagination :links="announcements.links" :from="announcements.from" :to="announcements.to" :total="announcements.total" />
        </div>
    </AppLayout>
</template>
