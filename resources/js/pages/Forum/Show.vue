<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import Pagination from '@/components/Pagination.vue';
import CategoryBadge from '@/components/Forum/CategoryBadge.vue';
import ReplyItem from '@/components/Forum/ReplyItem.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Eye, Lock, MessageCircle, Pin, Trash2 } from 'lucide-vue-next';
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

function roleBadgeVariant(role: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (role === 'admin') return 'destructive';
    if (role === 'guru') return 'default';
    return 'secondary';
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

        <div class="mx-auto max-w-4xl space-y-6 p-6">
            <FlashMessage />

            <!-- Thread Header -->
            <div class="rounded-lg border p-6">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 flex-wrap mb-2">
                            <Pin v-if="thread.is_pinned" class="size-4 text-primary" />
                            <Lock v-if="thread.is_locked" class="size-4 text-muted-foreground" />
                            <h1 class="text-2xl font-bold">{{ thread.title }}</h1>
                        </div>

                        <div class="flex items-center gap-2 flex-wrap text-sm text-muted-foreground">
                            <CategoryBadge :category="thread.category" />
                            <span v-if="thread.user">
                                oleh <span class="font-medium">{{ thread.user.name }}</span>
                            </span>
                            <Badge v-if="thread.user" :variant="roleBadgeVariant(thread.user.role)" class="text-xs">
                                {{ thread.user.role === 'admin' ? 'Admin' : thread.user.role === 'guru' ? 'Guru' : 'Siswa' }}
                            </Badge>
                            <span>&middot;</span>
                            <span>{{ formatDate(thread.created_at) }}</span>
                            <span>&middot;</span>
                            <span class="flex items-center gap-1"><Eye class="size-3" /> {{ thread.view_count }}</span>
                        </div>
                    </div>

                    <div class="flex gap-2 shrink-0">
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
                </div>

                <div class="mt-4 prose prose-sm dark:prose-invert max-w-none" v-html="thread.content" />
            </div>

            <!-- Replies -->
            <div class="rounded-lg border p-6">
                <h2 class="text-lg font-semibold flex items-center gap-2 mb-4">
                    <MessageCircle class="size-5" />
                    Balasan ({{ thread.reply_count }})
                </h2>

                <div v-if="replies.data.length">
                    <ReplyItem
                        v-for="reply in replies.data"
                        :key="reply.id"
                        :reply="reply"
                        @delete="confirmDelete('reply', $event)"
                    />
                    <Pagination :links="replies.links" :from="replies.from" :to="replies.to" :total="replies.total" />
                </div>
                <p v-else class="text-sm text-muted-foreground">Belum ada balasan.</p>
            </div>

            <!-- Reply Form -->
            <div v-if="can.reply" class="rounded-lg border p-6">
                <h3 class="text-lg font-semibold mb-3">Tulis Balasan</h3>
                <form @submit.prevent="submitReply" class="space-y-3">
                    <Textarea
                        v-model="replyForm.content"
                        placeholder="Tulis balasan..."
                        rows="4"
                        :class="{ 'border-destructive': replyForm.errors.content }"
                    />
                    <p v-if="replyForm.errors.content" class="text-sm text-destructive">{{ replyForm.errors.content }}</p>
                    <Button type="submit" :disabled="replyForm.processing">
                        {{ replyForm.processing ? 'Mengirim...' : 'Kirim Balasan' }}
                    </Button>
                </form>
            </div>
            <div v-else class="rounded-lg border p-6 text-center text-muted-foreground">
                <Lock class="mx-auto size-6 mb-2" />
                <p>Thread ini sudah dikunci. Tidak bisa mengirim balasan.</p>
            </div>

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
