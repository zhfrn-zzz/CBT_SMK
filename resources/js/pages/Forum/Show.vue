<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import Pagination from '@/components/Pagination.vue';
import CategoryBadge from '@/components/Forum/CategoryBadge.vue';
import ReplyItem from '@/components/Forum/ReplyItem.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Eye, Lock, MessageCircle, MessagesSquare, Pin, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem, ForumPermissions, ForumReply, ForumThread, PaginatedData } from '@/types';

const props = defineProps<{
    thread: ForumThread;
    replies: PaginatedData<ForumReply>;
    can: ForumPermissions;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Forum', href: '/forum' },
    { title: props.thread.title, href: `/forum/${props.thread.id}` },
];

const page = usePage();
const currentUser = computed(() => (page.props.auth as { user: { id: number; role: string } }).user);

const replyForm = useForm({ content: '' });
const deleteDialogOpen = ref(false);
const deleteTarget = ref<{ type: 'thread' | 'reply'; id: number } | null>(null);

function submitReply() {
    replyForm.post(`/forum/${props.thread.id}/reply`, {
        preserveScroll: true,
        onSuccess: () => { replyForm.reset(); },
    });
}

function confirmDelete(type: 'thread' | 'reply', id: number) {
    deleteTarget.value = { type, id };
    deleteDialogOpen.value = true;
}

function executeDelete() {
    if (!deleteTarget.value) return;
    const { type, id } = deleteTarget.value;

    if (type === 'thread') {
        router.delete(`/forum/${id}`);
    } else {
        router.delete(`/forum/reply/${id}`, { preserveScroll: true });
    }
    deleteDialogOpen.value = false;
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

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="thread.title" />

        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <PageHeader :title="thread.title" :icon="MessagesSquare">
                <template #actions>
                    <div class="flex items-center gap-2">
                        <Button v-if="can.pin" variant="outline" size="sm" @click="router.post(`/forum/${thread.id}/toggle-pin`)">
                            <Pin class="mr-1 size-4" /> {{ thread.is_pinned ? 'Unpin' : 'Pin' }}
                        </Button>
                        <Button v-if="can.lock" variant="outline" size="sm" @click="router.post(`/forum/${thread.id}/toggle-lock`)">
                            <Lock class="mr-1 size-4" /> {{ thread.is_locked ? 'Buka' : 'Kunci' }}
                        </Button>
                        <Button v-if="can.delete" variant="destructive" size="sm" @click="confirmDelete('thread', thread.id)">
                            <Trash2 class="mr-1 size-4" /> Hapus
                        </Button>
                    </div>
                </template>
            </PageHeader>

            <!-- Thread Content Card -->
            <Card class="mx-auto w-full max-w-4xl">
                <CardContent class="space-y-4 p-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <CategoryBadge :category="thread.category" />
                        <Pin v-if="thread.is_pinned" class="size-4 text-blue-500" />
                        <Lock v-if="thread.is_locked" class="size-4 text-muted-foreground" />
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-medium">
                            {{ thread.user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ thread.user?.name }}</span>
                                <span
                                    v-if="thread.user"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="roleClasses(thread.user.role)"
                                >
                                    {{ roleLabel(thread.user.role) }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <span>{{ formatDate(thread.created_at) }}</span>
                                <span>&middot;</span>
                                <span class="flex items-center gap-1"><Eye class="size-3" /> {{ thread.view_count }}</span>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <div class="prose prose-sm max-w-none dark:prose-invert" v-html="thread.content" />
                </CardContent>
            </Card>

            <!-- Replies -->
            <Card class="mx-auto w-full max-w-4xl">
                <CardContent class="p-6">
                    <h2 class="flex items-center gap-2 text-lg font-semibold">
                        <MessageCircle class="size-5" />
                        {{ thread.reply_count }} Balasan
                    </h2>

                    <Separator class="my-4" />

                    <div v-if="replies.data.length" class="space-y-1">
                        <ReplyItem
                            v-for="reply in replies.data"
                            :key="reply.id"
                            :reply="reply"
                            @delete="confirmDelete('reply', $event)"
                        />
                        <Pagination :links="replies.links" :from="replies.from" :to="replies.to" :total="replies.total" />
                    </div>
                    <p v-else class="py-4 text-center text-sm text-muted-foreground">Belum ada balasan.</p>
                </CardContent>
            </Card>

            <!-- Reply Form -->
            <Card v-if="can.reply" class="mx-auto w-full max-w-4xl">
                <CardContent class="p-6">
                    <h3 class="mb-3 text-lg font-semibold">Tulis Balasan</h3>
                    <form @submit.prevent="submitReply" class="space-y-3">
                        <div class="space-y-2">
                            <Label for="reply-content">Balasan</Label>
                            <Textarea
                                id="reply-content"
                                v-model="replyForm.content"
                                placeholder="Tulis balasan..."
                                rows="4"
                                :class="{ 'border-destructive': replyForm.errors.content }"
                            />
                            <p v-if="replyForm.errors.content" class="text-sm text-destructive">{{ replyForm.errors.content }}</p>
                        </div>
                        <div class="flex justify-end">
                            <LoadingButton type="submit" :loading="replyForm.processing">
                                Kirim Balasan
                            </LoadingButton>
                        </div>
                    </form>
                </CardContent>
            </Card>
            <Card v-else class="mx-auto w-full max-w-4xl">
                <CardContent class="p-6 text-center text-muted-foreground">
                    <Lock class="mx-auto size-6 mb-2" />
                    <p>Thread ini sudah dikunci. Tidak bisa mengirim balasan.</p>
                </CardContent>
            </Card>

            <!-- Delete Dialog -->
            <AlertDialog v-model:open="deleteDialogOpen">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Konfirmasi Hapus</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus {{ deleteTarget?.type === 'thread' ? 'thread ini' : 'balasan ini' }}?
                            Tindakan ini tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" @click="executeDelete">Hapus</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </AppLayout>
</template>
