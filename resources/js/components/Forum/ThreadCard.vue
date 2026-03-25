<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Lock, MessageCircle, Pin, Eye } from 'lucide-vue-next';
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

function roleClasses(role: string): string {
    if (role === 'admin') return 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-400';
    if (role === 'guru') return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400';
    return 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-400';
}

function roleLabel(role: string): string {
    if (role === 'admin') return 'Admin';
    if (role === 'guru') return 'Guru';
    return 'Siswa';
}
</script>

<template>
    <Link
        :href="`/forum/${thread.id}`"
        class="block rounded-xl border bg-card p-4 transition-shadow hover:shadow-sm"
        :class="{
            'border-blue-100 bg-blue-50/50 dark:border-blue-900 dark:bg-blue-950/30': thread.is_pinned,
        }"
    >
        <div class="flex items-start gap-3">
            <!-- Author avatar -->
            <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium">
                {{ thread.user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
            </div>

            <div class="min-w-0 flex-1">
                <div class="mb-1 flex flex-wrap items-center gap-2">
                    <Pin v-if="thread.is_pinned" class="size-4 shrink-0 text-blue-500" />
                    <Lock v-if="thread.is_locked" class="size-4 shrink-0 text-muted-foreground" />
                    <CategoryBadge :category="thread.category" />
                    <h3 class="text-base font-semibold hover:text-primary">{{ thread.title }}</h3>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <span v-if="thread.user" class="flex items-center gap-1.5">
                        <span class="font-medium text-foreground">{{ thread.user.name }}</span>
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                            :class="roleClasses(thread.user.role)"
                        >
                            {{ roleLabel(thread.user.role) }}
                        </span>
                    </span>
                    <span>&middot;</span>
                    <span>{{ timeAgo(thread.created_at) }}</span>
                </div>

                <p class="mt-1.5 line-clamp-2 text-sm text-muted-foreground">
                    {{ thread.content.replace(/<[^>]*>/g, '').slice(0, 150) }}
                </p>
            </div>

            <div class="flex shrink-0 flex-col items-end gap-1 text-sm text-muted-foreground">
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
