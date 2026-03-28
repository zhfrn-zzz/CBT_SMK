<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Award, Bell, Calendar, ClipboardList, Download, Info, Megaphone, MessageCircle, Trash2, UserCheck } from 'lucide-vue-next';
import type { BreadcrumbItem, PaginatedData } from '@/types';
import type { NotificationItem } from '@/types/notification';

const props = defineProps<{
    notifications: PaginatedData<NotificationItem>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Notifikasi', href: '/notifications/list' },
];

const filter = ref<'all' | 'unread'>('all');

const filteredNotifications = computed(() => {
    if (filter.value === 'unread') {
        return props.notifications.data.filter((n) => !n.read_at);
    }
    return props.notifications.data;
});

const typeIcons: Record<string, typeof Calendar> = {
    ujian_dijadwalkan: Calendar,
    deadline_tugas: ClipboardList,
    nilai_dipublikasi: Award,
    materi_baru: Info,
    pengumuman_baru: Megaphone,
    forum_reply: MessageCircle,
    presensi: UserCheck,
    export_ready: Download,
    cleanup_completed: ClipboardList,
};

const typeColors: Record<string, string> = {
    ujian_dijadwalkan: 'text-blue-500',
    deadline_tugas: 'text-amber-500',
    nilai_dipublikasi: 'text-emerald-500',
    materi_baru: 'text-blue-500',
    pengumuman_baru: 'text-purple-500',
    forum_reply: 'text-blue-500',
    presensi: 'text-emerald-500',
    export_ready: 'text-emerald-500',
    cleanup_completed: 'text-blue-500',
};

const typeBadgeVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    ujian_dijadwalkan: 'default',
    deadline_tugas: 'destructive',
    nilai_dipublikasi: 'secondary',
    materi_baru: 'outline',
    pengumuman_baru: 'outline',
    export_ready: 'secondary',
    cleanup_completed: 'outline',
};

function markAllAsRead() {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
}

function openNotification(notif: NotificationItem) {
    if (!notif.read_at) {
        axios.post(`/notifications/${notif.id}/read`).catch(() => {});
    }
    if (notif.data.type === 'export_ready') {
        window.location.href = notif.data.action_url;
    } else {
        router.visit(notif.data.action_url);
    }
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

        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <PageHeader title="Notifikasi" description="Semua notifikasi Anda" :icon="Bell">
                <template #actions>
                    <Button variant="outline" size="sm" @click="markAllAsRead">
                        Tandai semua dibaca
                    </Button>
                </template>
            </PageHeader>

            <!-- Filter tabs -->
            <div class="flex gap-2">
                <Button
                    :variant="filter === 'all' ? 'default' : 'outline'"
                    size="sm"
                    @click="filter = 'all'"
                >
                    Semua
                </Button>
                <Button
                    :variant="filter === 'unread' ? 'default' : 'outline'"
                    size="sm"
                    @click="filter = 'unread'"
                >
                    Belum Dibaca
                </Button>
            </div>

            <!-- Notifications List -->
            <div v-if="filteredNotifications.length > 0" class="space-y-2">
                <div
                    v-for="notif in filteredNotifications"
                    :key="notif.id"
                    class="flex cursor-pointer items-start gap-4 rounded-lg border p-4 transition-colors hover:bg-muted/50"
                    :class="{
                        'border-l-2 border-l-primary bg-primary/5': !notif.read_at,
                    }"
                    @click="openNotification(notif)"
                >
                    <div class="mt-0.5 shrink-0">
                        <component
                            :is="typeIcons[notif.data.type] ?? Bell"
                            class="size-5"
                            :class="typeColors[notif.data.type] ?? 'text-muted-foreground'"
                        />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium" :class="{ 'font-semibold': !notif.read_at }">{{ notif.data.title }}</span>
                            <Badge :variant="typeBadgeVariant[notif.data.type] ?? 'outline'" class="text-xs">
                                {{ notif.data.type.replace(/_/g, ' ') }}
                            </Badge>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">{{ notif.data.message }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ timeAgo(notif.created_at) }}</p>
                    </div>

                    <Button
                        variant="ghost"
                        size="icon"
                        class="shrink-0 text-muted-foreground hover:text-destructive"
                        @click.stop="deleteNotification(notif.id)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>

            <EmptyState
                v-else
                :icon="Bell"
                :title="filter === 'unread' ? 'Tidak ada notifikasi belum dibaca' : 'Belum ada notifikasi'"
                :description="filter === 'unread' ? 'Semua notifikasi sudah dibaca.' : 'Notifikasi akan muncul di sini.'"
            />

            <Pagination
                :links="notifications.links"
                :from="notifications.from"
                :to="notifications.to"
                :total="notifications.total"
            />
        </div>
    </AppLayout>
</template>
