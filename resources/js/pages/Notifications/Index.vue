<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import type { BreadcrumbItem, PaginatedData } from '@/types';
import type { NotificationItem } from '@/types/notification';

const props = defineProps<{
    notifications: PaginatedData<NotificationItem>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Notifikasi', href: '/notifications/list' },
];

const typeIcons: Record<string, string> = {
    ujian_dijadwalkan: '📝',
    deadline_tugas: '⏰',
    nilai_dipublikasi: '🎯',
    materi_baru: '📚',
    pengumuman_baru: '📢',
};

const typeBadgeVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    ujian_dijadwalkan: 'default',
    deadline_tugas: 'destructive',
    nilai_dipublikasi: 'secondary',
    materi_baru: 'outline',
    pengumuman_baru: 'outline',
};

function markAllAsRead() {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
}

function openNotification(notif: NotificationItem) {
    if (!notif.read_at) {
        // Fire-and-forget mark as read before navigating
        axios.post(`/notifications/${notif.id}/read`).catch(() => {});
    }
    router.visit(notif.data.action_url);
}

function deleteNotification(id: string) {
    router.delete(`/notifications/${id}`, { preserveScroll: true });
}

function timeAgo(dateStr: string): string {
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'Baru saja';
    if (mins < 60) return `${mins} mnt lalu`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs} jam lalu`;
    return `${Math.floor(hrs / 24)} hari lalu`;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Notifikasi" />

        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Notifikasi</h1>
                <Button variant="outline" size="sm" @click="markAllAsRead">
                    Tandai semua dibaca
                </Button>
            </div>

            <FlashMessage />

            <div class="space-y-2">
                <div
                    v-if="notifications.data.length === 0"
                    class="rounded-lg border p-8 text-center text-muted-foreground"
                >
                    Belum ada notifikasi
                </div>

                <div
                    v-for="notif in notifications.data"
                    :key="notif.id"
                    :class="[
                        'flex items-start gap-4 rounded-lg border p-4 transition-colors',
                        notif.read_at ? 'bg-background' : 'bg-muted/50',
                    ]"
                >
                    <span class="mt-0.5 text-2xl">{{ typeIcons[notif.data.type] ?? '🔔' }}</span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ notif.data.title }}</span>
                            <Badge :variant="typeBadgeVariant[notif.data.type] ?? 'outline'" class="text-xs">
                                {{ notif.data.type.replace(/_/g, ' ') }}
                            </Badge>
                            <span v-if="!notif.read_at" class="h-2 w-2 rounded-full bg-blue-500" />
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">{{ notif.data.message }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ timeAgo(notif.created_at) }}</p>
                    </div>

                    <div class="flex shrink-0 gap-2">
                        <Button variant="outline" size="sm" @click="openNotification(notif)">
                            Buka
                        </Button>
                        <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="deleteNotification(notif.id)">
                            Hapus
                        </Button>
                    </div>
                </div>
            </div>

            <Pagination
                :links="notifications.links"
                :from="notifications.from"
                :to="notifications.to"
                :total="notifications.total"
            />
        </div>
    </AppLayout>
</template>
