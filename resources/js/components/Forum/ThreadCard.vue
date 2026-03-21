<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Lock, MessageCircle, Pin, Eye } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import CategoryBadge from './CategoryBadge.vue';
import type { ForumThread } from '@/types';

defineProps<{
    thread: ForumThread;
}>();

function timeAgo(dateStr: string): string {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now.getTime() - date.getTime()) / 1000);
    if (diff < 60) return 'baru saja';
    if (diff < 3600) return `${Math.floor(diff / 60)} menit lalu`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} jam lalu`;
    if (diff < 604800) return `${Math.floor(diff / 86400)} hari lalu`;
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function roleBadgeVariant(role: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (role === 'admin') return 'destructive';
    if (role === 'guru') return 'default';
    return 'secondary';
}
</script>

<template>
    <Link
        :href="`/forum/${thread.id}`"
        class="block rounded-lg border p-4 transition-colors hover:bg-muted/50"
        :class="{ 'border-primary/30 bg-primary/5': thread.is_pinned }"
    >
        <div class="flex items-start gap-3">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <Pin v-if="thread.is_pinned" class="size-4 text-primary shrink-0" />
                    <Lock v-if="thread.is_locked" class="size-4 text-muted-foreground shrink-0" />
                    <h3 class="font-semibold truncate">{{ thread.title }}</h3>
                </div>

                <div class="flex items-center gap-2 flex-wrap text-sm text-muted-foreground mb-2">
                    <CategoryBadge :category="thread.category" />
                    <span v-if="thread.user">
                        oleh <span class="font-medium">{{ thread.user.name }}</span>
                    </span>
                    <Badge v-if="thread.user" :variant="roleBadgeVariant(thread.user.role)" class="text-xs">
                        {{ thread.user.role === 'admin' ? 'Admin' : thread.user.role === 'guru' ? 'Guru' : 'Siswa' }}
                    </Badge>
                    <span>&middot;</span>
                    <span>{{ timeAgo(thread.created_at) }}</span>
                </div>

                <p class="text-sm text-muted-foreground line-clamp-2">
                    {{ thread.content.replace(/<[^>]*>/g, '').slice(0, 150) }}
                </p>
            </div>

            <div class="flex flex-col items-end gap-1 shrink-0 text-sm text-muted-foreground">
                <div class="flex items-center gap-1">
                    <MessageCircle class="size-4" />
                    <span>{{ thread.replies_count ?? thread.reply_count }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <Eye class="size-4" />
                    <span>{{ thread.view_count }}</span>
                </div>
                <span v-if="thread.last_reply_at" class="text-xs">
                    {{ timeAgo(thread.last_reply_at) }}
                </span>
            </div>
        </div>
    </Link>
</template>
