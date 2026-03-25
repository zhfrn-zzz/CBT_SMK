<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import type { ForumReply } from '@/types';

const props = defineProps<{
    reply: ForumReply;
}>();

const emit = defineEmits<{
    delete: [id: number];
}>();

const page = usePage();
const currentUser = computed(() => (page.props.auth as { user: { id: number; role: string } }).user);

const canDelete = computed(() => {
    const user = currentUser.value;
    return user.role === 'admin' || user.role === 'guru' || props.reply.user_id === user.id;
});

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
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <div class="border-l-2 border-primary/20 py-4 pl-4">
        <div class="flex gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-medium">
                {{ reply.user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="mb-1 flex items-center gap-2">
                    <span class="text-sm font-medium">{{ reply.user?.name ?? 'Deleted User' }}</span>
                    <span
                        v-if="reply.user"
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="roleClasses(reply.user.role)"
                    >
                        {{ roleLabel(reply.user.role) }}
                    </span>
                    <span class="text-xs text-muted-foreground">{{ formatDate(reply.created_at) }}</span>
                </div>
                <div class="prose prose-sm max-w-none dark:prose-invert" v-html="reply.content" />
            </div>
            <Button
                v-if="canDelete"
                variant="ghost"
                size="icon"
                class="shrink-0 text-muted-foreground hover:text-destructive"
                @click="emit('delete', reply.id)"
            >
                <Trash2 class="size-4" />
            </Button>
        </div>
    </div>
</template>
