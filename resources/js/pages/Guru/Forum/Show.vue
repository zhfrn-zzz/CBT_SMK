<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import { Lock, Pin, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem, DiscussionReply, DiscussionThread, PaginatedData } from '@/types';

const props = defineProps<{
    thread: DiscussionThread;
    replies: PaginatedData<DiscussionReply>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Forum', href: '/guru/forum' },
    { title: props.thread.title, href: `/guru/forum/${props.thread.id}` },
];

const replyForm = useForm({ content: '' });

function submitReply() {
    replyForm.post(`/guru/forum/${props.thread.id}/reply`, { onSuccess: () => replyForm.reset() });
}

function deleteReply(replyId: number) {
    router.delete(`/guru/forum/reply/${replyId}`, { preserveScroll: true });
}

function deleteThread() {
    if (confirm('Hapus thread ini beserta semua balasannya?')) {
        router.delete(`/guru/forum/${props.thread.id}`);
    }
}

function togglePin() {
    router.post(`/guru/forum/${props.thread.id}/toggle-pin`, {}, { preserveScroll: true });
}

function toggleLock() {
    router.post(`/guru/forum/${props.thread.id}/toggle-lock`, {}, { preserveScroll: true });
}

function timeAgo(date: string) {
    const diff = Date.now() - new Date(date).getTime();
    const m = Math.floor(diff / 60000);
    if (m < 1) return 'baru saja';
    if (m < 60) return `${m} menit lalu`;
    const h = Math.floor(m / 60);
    if (h < 24) return `${h} jam lalu`;
    return new Date(date).toLocaleDateString('id-ID');
}
</script>

<template>
    <Head :title="thread.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl p-4 space-y-4">
            <FlashMessage />

            <!-- Thread header -->
            <div class="rounded-lg border p-4 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <Pin v-if="thread.is_pinned" class="size-4 text-primary" />
                            <Lock v-if="thread.is_locked" class="size-4 text-muted-foreground" />
                            <h2 class="text-xl font-semibold">{{ thread.title }}</h2>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Oleh <strong>{{ thread.user?.name }}</strong> · {{ timeAgo(thread.created_at) }}
                        </p>
                    </div>
                    <div class="flex gap-1 shrink-0">
                        <Button variant="outline" size="sm" @click="togglePin">{{ thread.is_pinned ? 'Unpin' : 'Pin' }}</Button>
                        <Button variant="outline" size="sm" @click="toggleLock">{{ thread.is_locked ? 'Buka' : 'Kunci' }}</Button>
                        <Button variant="ghost" size="icon-sm" @click="deleteThread"><Trash2 class="size-4 text-destructive" /></Button>
                    </div>
                </div>
                <div class="prose prose-sm max-w-none text-sm">
                    {{ thread.content }}
                </div>
            </div>

            <!-- Replies -->
            <div class="space-y-3">
                <p class="font-medium text-sm text-muted-foreground">{{ thread.reply_count }} Balasan</p>

                <div v-if="replies.data.length === 0" class="text-center text-muted-foreground py-4">
                    Belum ada balasan. Jadilah yang pertama membalas.
                </div>

                <div v-for="r in replies.data" :key="r.id" class="rounded-md border p-3 space-y-2">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium">{{ r.user?.name }}</p>
                        <div class="flex items-center gap-2">
                            <p class="text-xs text-muted-foreground">{{ timeAgo(r.created_at) }}</p>
                            <Button variant="ghost" size="icon-sm" @click="deleteReply(r.id)">
                                <Trash2 class="size-3 text-destructive" />
                            </Button>
                        </div>
                    </div>
                    <p class="text-sm whitespace-pre-wrap">{{ r.content }}</p>
                </div>

                <Pagination :links="replies.links" :from="replies.from" :to="replies.to" :total="replies.total" />
            </div>

            <!-- Reply form -->
            <div v-if="!thread.is_locked" class="rounded-lg border p-4 space-y-2">
                <p class="font-medium text-sm">Balas</p>
                <form @submit.prevent="submitReply" class="space-y-2">
                    <Textarea v-model="replyForm.content" placeholder="Tulis balasan..." rows="3" />
                    <p v-if="replyForm.errors.content" class="text-xs text-destructive">{{ replyForm.errors.content }}</p>
                    <Button type="submit" size="sm" :disabled="replyForm.processing">Kirim Balasan</Button>
                </form>
            </div>
            <div v-else class="rounded-lg border border-dashed p-4 text-center text-muted-foreground text-sm">
                Thread ini sudah dikunci, tidak bisa dibalas.
            </div>
        </div>
    </AppLayout>
</template>
