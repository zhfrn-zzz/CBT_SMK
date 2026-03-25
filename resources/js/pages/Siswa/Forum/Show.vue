<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import Pagination from '@/components/Pagination.vue';
import PageHeader from '@/Components/PageHeader.vue';
import LoadingButton from '@/Components/LoadingButton.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Lock, MessagesSquare, Pin, Trash2 } from 'lucide-vue-next';
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
    router.delete(`/siswa/forum/${props.thread.id}`);
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

            <PageHeader :title="thread.title" :icon="MessagesSquare">
                <template #actions>
                    <div class="flex items-center gap-2">
                        <Pin v-if="thread.is_pinned" class="size-4 text-primary" />
                        <Lock v-if="thread.is_locked" class="size-4 text-muted-foreground" />
                        <ConfirmDialog v-if="thread.user_id === currentUserId" title="Hapus Thread?" description="Thread dan semua balasan akan dihapus. Tindakan ini tidak dapat dibatalkan." confirm-label="Ya, Hapus" @confirm="deleteThread">
                            <Button variant="ghost" size="icon-sm">
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </ConfirmDialog>
                    </div>
                </template>
            </PageHeader>

            <Card>
                <CardContent class="p-4 space-y-2">
                    <p class="text-sm text-muted-foreground">
                        Oleh <strong>{{ thread.user?.name }}</strong> · {{ timeAgo(thread.created_at) }}
                    </p>
                    <p class="text-sm whitespace-pre-wrap">{{ thread.content }}</p>
                </CardContent>
            </Card>

            <div class="space-y-3">
                <p class="font-medium text-sm text-muted-foreground">{{ thread.reply_count }} Balasan</p>

                <EmptyState v-if="replies.data.length === 0" :icon="MessagesSquare" title="Belum ada balasan" description="Jadilah yang pertama membalas thread ini." />

                <Card v-for="r in replies.data" :key="r.id">
                    <CardContent class="p-4 space-y-1">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium">{{ r.user?.name }}</p>
                            <div class="flex items-center gap-2">
                                <p class="text-xs text-muted-foreground">{{ timeAgo(r.created_at) }}</p>
                                <ConfirmDialog v-if="r.user_id === currentUserId" title="Hapus Balasan?" description="Balasan ini akan dihapus." confirm-label="Ya, Hapus" @confirm="deleteReply(r.id)">
                                    <Button variant="ghost" size="icon-sm">
                                        <Trash2 class="size-3 text-destructive" />
                                    </Button>
                                </ConfirmDialog>
                            </div>
                        </div>
                        <p class="text-sm whitespace-pre-wrap">{{ r.content }}</p>
                    </CardContent>
                </Card>

                <Pagination :links="replies.links" :from="replies.from" :to="replies.to" :total="replies.total" />
            </div>

            <Card v-if="!thread.is_locked">
                <CardContent class="p-4 space-y-2">
                    <p class="font-medium text-sm">Balas</p>
                    <form @submit.prevent="submitReply" class="space-y-2">
                        <Textarea v-model="replyForm.content" placeholder="Tulis balasan..." rows="3" />
                        <p v-if="replyForm.errors.content" class="text-xs text-destructive">{{ replyForm.errors.content }}</p>
                        <LoadingButton type="submit" size="sm" :loading="replyForm.processing">Kirim</LoadingButton>
                    </form>
                </CardContent>
            </Card>
            <div v-else class="rounded-lg border border-dashed p-4 text-center text-muted-foreground text-sm">
                Thread ini sudah dikunci.
            </div>
        </div>
    </AppLayout>
</template>
