<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent } from '@/components/ui/card';
import { Lock, MessagesSquare, Pin, Trash2 } from 'lucide-vue-next';
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
    router.delete(`/guru/forum/${props.thread.id}`);
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

            <PageHeader :title="thread.title" :icon="MessagesSquare">
                <template #actions>
                    <Button variant="outline" size="sm" @click="togglePin">{{ thread.is_pinned ? 'Unpin' : 'Pin' }}</Button>
                    <Button variant="outline" size="sm" @click="toggleLock">{{ thread.is_locked ? 'Buka' : 'Kunci' }}</Button>
                    <ConfirmDialog
                        title="Hapus Thread"
                        description="Thread ini beserta semua balasannya akan dihapus permanen."
                        confirm-label="Hapus"
                        variant="destructive"
                        @confirm="deleteThread"
                    >
                        <Button variant="ghost" size="icon-sm"><Trash2 class="size-4 text-destructive" /></Button>
                    </ConfirmDialog>
                </template>
            </PageHeader>

            <!-- Thread content -->
            <Card>
                <CardContent class="p-4 space-y-3">
                    <p class="text-sm text-muted-foreground">
                        Oleh <strong>{{ thread.user?.name }}</strong> · {{ timeAgo(thread.created_at) }}
                    </p>
                    <div class="prose prose-sm max-w-none text-sm">
                        {{ thread.content }}
                    </div>
                </CardContent>
            </Card>

            <!-- Replies -->
            <div class="space-y-3">
                <p class="font-medium text-sm text-muted-foreground">{{ thread.reply_count }} Balasan</p>

                <div v-if="replies.data.length === 0" class="text-center text-muted-foreground py-4">
                    Belum ada balasan. Jadilah yang pertama membalas.
                </div>

                <div v-for="r in replies.data" :key="r.id" class="border-l-2 border-primary/20 py-4 pl-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium">
                                {{ r.user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
                            </div>
                            <p class="text-sm font-medium">{{ r.user?.name }}</p>
                            <span class="text-xs text-muted-foreground">{{ timeAgo(r.created_at) }}</span>
                        </div>
                        <ConfirmDialog
                            title="Hapus Balasan"
                            description="Balasan ini akan dihapus permanen."
                            confirm-label="Hapus"
                            variant="destructive"
                            @confirm="deleteReply(r.id)"
                        >
                            <Button variant="ghost" size="icon-sm">
                                <Trash2 class="size-3 text-destructive" />
                            </Button>
                        </ConfirmDialog>
                    </div>
                    <p class="mt-2 whitespace-pre-wrap text-sm">{{ r.content }}</p>
                </div>

                <Pagination :links="replies.links" :from="replies.from" :to="replies.to" :total="replies.total" />
            </div>

            <!-- Reply form -->
            <Card v-if="!thread.is_locked">
                <CardContent class="space-y-2 p-4">
                    <Label class="font-semibold text-sm">Balas</Label>
                    <form @submit.prevent="submitReply" class="space-y-2">
                        <Textarea v-model="replyForm.content" placeholder="Tulis balasan..." rows="3" />
                        <p v-if="replyForm.errors.content" class="text-xs text-destructive">{{ replyForm.errors.content }}</p>
                        <div class="flex justify-end">
                            <LoadingButton :loading="replyForm.processing" type="submit" size="sm">Kirim Balasan</LoadingButton>
                        </div>
                    </form>
                </CardContent>
            </Card>
            <div v-else class="rounded-lg border border-dashed p-4 text-center text-muted-foreground text-sm">
                Thread ini sudah dikunci, tidak bisa dibalas.
            </div>
        </div>
    </AppLayout>
</template>
