<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Lock, Pin, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem, DiscussionReply, DiscussionThread, PaginatedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const currentUserId = (page.props.auth as any).user.id;

const props = defineProps<{
    thread: DiscussionThread;
    replies: PaginatedData<DiscussionReply>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Forum', href: '/siswa/forum' },
    { title: props.thread.title, href: `/siswa/forum/${props.thread.id}` },
];

const replyForm = useForm({ content: '' });

function submitReply() {
    replyForm.post(`/siswa/forum/${props.thread.id}/reply`, { onSuccess: () => replyForm.reset() });
}

function deleteReply(replyId: number) {
    router.delete(`/siswa/forum/reply/${replyId}`, { preserveScroll: true });
}

function deleteThread() {
    if (confirm('Hapus thread ini?')) {
        router.delete(`/siswa/forum/${props.thread.id}`);
    }
}

function timeAgo(date: string) {
    const diff = Date.now() - new Date(date).getTime();
    const m = Math.floor(diff / 60000);
    if (m < 1) return 'baru saja';
    if (m < 60) return `${m} mnt lalu`;
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
                    <Button v-if="thread.user_id === currentUserId" variant="ghost" size="icon-sm" @click="deleteThread">
                        <Trash2 class="size-4 text-destructive" />
                    </Button>
                </div>
                <p class="text-sm whitespace-pre-wrap">{{ thread.content }}</p>
            </div>

            <div class="space-y-3">
                <p class="font-medium text-sm text-muted-foreground">{{ thread.reply_count }} Balasan</p>

                <div v-if="replies.data.length === 0" class="text-center text-muted-foreground py-4 text-sm">
                    Belum ada balasan.
                </div>

                <div v-for="r in replies.data" :key="r.id" class="rounded-md border p-3 space-y-1">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium">{{ r.user?.name }}</p>
                        <div class="flex items-center gap-2">
                            <p class="text-xs text-muted-foreground">{{ timeAgo(r.created_at) }}</p>
                            <Button v-if="r.user_id === currentUserId" variant="ghost" size="icon-sm" @click="deleteReply(r.id)">
                                <Trash2 class="size-3 text-destructive" />
                            </Button>
                        </div>
                    </div>
                    <p class="text-sm whitespace-pre-wrap">{{ r.content }}</p>
                </div>

                <Pagination :links="replies.links" :from="replies.from" :to="replies.to" :total="replies.total" />
            </div>

            <div v-if="!thread.is_locked" class="rounded-lg border p-4 space-y-2">
                <p class="font-medium text-sm">Balas</p>
                <form @submit.prevent="submitReply" class="space-y-2">
                    <Textarea v-model="replyForm.content" placeholder="Tulis balasan..." rows="3" />
                    <p v-if="replyForm.errors.content" class="text-xs text-destructive">{{ replyForm.errors.content }}</p>
                    <Button type="submit" size="sm" :disabled="replyForm.processing">Kirim</Button>
                </form>
            </div>
            <div v-else class="rounded-lg border border-dashed p-4 text-center text-muted-foreground text-sm">
                Thread ini sudah dikunci.
            </div>
        </div>
    </AppLayout>
</template>
