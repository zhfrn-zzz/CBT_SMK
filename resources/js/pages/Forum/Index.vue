<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import ThreadCard from '@/components/Forum/ThreadCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { MessagesSquare, Plus, Search } from 'lucide-vue-next';
import type { BreadcrumbItem, ForumCategory, ForumThread, PaginatedData } from '@/types';

const props = defineProps<{
    threads: PaginatedData<ForumThread>;
    categories: ForumCategory[];
    filters: { category: string | null; search: string | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Forum', href: '/forum' },
];

const search = ref(props.filters.search ?? '');
const activeCategory = ref(props.filters.category ?? '');

let debounceTimer: ReturnType<typeof setTimeout>;

function applyFilters() {
    const params: Record<string, string> = {};
    if (search.value) params.search = search.value;
    if (activeCategory.value) params.category = activeCategory.value;

    router.get('/forum', params, { preserveState: true, replace: true });
}

watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 300);
});

function filterByCategory(slug: string) {
    activeCategory.value = activeCategory.value === slug ? '' : slug;
    applyFilters();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Forum" />

        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <PageHeader title="Forum Diskusi" description="Diskusi dan berbagi informasi" :icon="MessagesSquare">
                <template #actions>
                    <Button as-child>
                        <Link href="/forum/create">
                            <Plus class="mr-2 size-4" />
                            Buat Thread
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <!-- Search & Filter -->
            <div class="space-y-3">
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="search"
                        placeholder="Cari thread..."
                        class="h-11 pl-10"
                    />
                </div>
                <div class="flex flex-wrap gap-2">
                    <Badge
                        :variant="!activeCategory ? 'default' : 'outline'"
                        class="cursor-pointer"
                        @click="filterByCategory('')"
                    >
                        Semua
                    </Badge>
                    <Badge
                        v-for="cat in categories"
                        :key="cat.id"
                        :variant="activeCategory === cat.slug ? 'default' : 'outline'"
                        class="cursor-pointer"
                        :style="activeCategory === cat.slug && cat.color ? { backgroundColor: cat.color, borderColor: cat.color } : {}"
                        @click="filterByCategory(cat.slug)"
                    >
                        {{ cat.name }}
                    </Badge>
                </div>
            </div>

            <!-- Thread List -->
            <div v-if="threads.data.length" class="space-y-3">
                <ThreadCard v-for="thread in threads.data" :key="thread.id" :thread="thread" />
                <Pagination :links="threads.links" :from="threads.from" :to="threads.to" :total="threads.total" />
            </div>

            <EmptyState
                v-else
                :icon="MessagesSquare"
                title="Belum ada diskusi"
                description="Mulai diskusi pertama di forum!"
            >
                <template #action>
                    <Button as-child>
                        <Link href="/forum/create">
                            <Plus class="mr-2 size-4" />
                            Buat Thread
                        </Link>
                    </Button>
                </template>
            </EmptyState>
        </div>
    </AppLayout>
</template>
