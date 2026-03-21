<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Trash2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
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

function roleBadgeVariant(role: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (role === 'admin') return 'destructive';
    if (role === 'guru') return 'default';
    return 'secondary';
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
    <div class="flex gap-3 py-4 border-b last:border-b-0">
        <div class="size-10 rounded-full bg-muted flex items-center justify-center shrink-0 text-sm font-medium">
            {{ reply.user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
                <span class="font-medium text-sm">{{ reply.user?.name ?? 'Deleted User' }}</span>
                <Badge v-if="reply.user" :variant="roleBadgeVariant(reply.user.role)" class="text-xs">
                    {{ reply.user.role === 'admin' ? 'Admin' : reply.user.role === 'guru' ? 'Guru' : 'Siswa' }}
                </Badge>
                <span class="text-xs text-muted-foreground">{{ formatDate(reply.created_at) }}</span>
            </div>
            <div class="text-sm prose prose-sm dark:prose-invert max-w-none" v-html="reply.content" />
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
</template>
