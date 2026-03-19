<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Bell } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { NotificationItem } from '@/types/notification';

const page = usePage();
const unreadCount = computed(() => (page.props.auth as Record<string, unknown>).unread_notifications_count as number ?? 0);

const notifications = ref<NotificationItem[]>([]);
const isLoading = ref(false);
const isOpen = ref(false);

async function fetchNotifications() {
    if (isLoading.value) return;
    isLoading.value = true;
    try {
        const { data } = await axios.get('/notifications');
        notifications.value = data.notifications.data.slice(0, 10);
    } finally {
        isLoading.value = false;
    }
}

function onOpenChange(open: boolean) {
    isOpen.value = open;
    if (open) fetchNotifications();
}

async function markAsRead(notification: NotificationItem) {
    if (!notification.read_at) {
        await axios.post(`/notifications/${notification.id}/read`);
    }
    router.visit(notification.data.action_url);
}

function markAllAsRead() {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
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

const typeIcons: Record<string, string> = {
    ujian_dijadwalkan: '📝',
    deadline_tugas: '⏰',
    nilai_dipublikasi: '🎯',
    materi_baru: '📚',
    pengumuman_baru: '📢',
};
</script>

<template>
    <DropdownMenu @update:open="onOpenChange">
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="relative">
                <Bell class="h-5 w-5" />
                <Badge
                    v-if="unreadCount > 0"
                    class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full p-0 text-[10px]"
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </Badge>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent class="w-80" align="end">
            <DropdownMenuLabel class="flex items-center justify-between">
                <span>Notifikasi</span>
                <Button
                    v-if="unreadCount > 0"
                    variant="ghost"
                    size="sm"
                    class="h-auto p-0 text-xs text-muted-foreground"
                    @click.prevent="markAllAsRead"
                >
                    Tandai semua dibaca
                </Button>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />

            <div v-if="isLoading" class="py-6 text-center text-sm text-muted-foreground">
                Memuat...
            </div>

            <div v-else-if="notifications.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                Tidak ada notifikasi
            </div>

            <template v-else>
                <DropdownMenuItem
                    v-for="notif in notifications"
                    :key="notif.id"
                    class="flex cursor-pointer items-start gap-2 px-3 py-2"
                    @click="markAsRead(notif)"
                >
                    <span class="mt-0.5 text-base">{{ typeIcons[notif.data.type] ?? '🔔' }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1">
                            <span class="truncate text-sm font-medium">{{ notif.data.title }}</span>
                            <span v-if="!notif.read_at" class="h-2 w-2 shrink-0 rounded-full bg-blue-500" />
                        </div>
                        <p class="line-clamp-2 text-xs text-muted-foreground">{{ notif.data.message }}</p>
                        <p class="mt-0.5 text-[10px] text-muted-foreground">{{ timeAgo(notif.created_at) }}</p>
                    </div>
                </DropdownMenuItem>
            </template>

            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
                <a href="/notifications/list" class="cursor-pointer justify-center text-sm text-muted-foreground">
                    Lihat semua notifikasi
                </a>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
