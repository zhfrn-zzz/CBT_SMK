<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import Pagination from '@/components/Pagination.vue';
import ThreadCard from '@/components/Forum/ThreadCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Plus, Search, MessageCircle } from 'lucide-vue-next';
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

        <div class="mx-auto max-w-4xl space-y-6 p-6">
            <FlashMessage />

            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Forum Diskusi</h1>
                <Button as-child>
                    <Link href="/forum/create">
                        <Plus class="mr-2 size-4" />
                        Buat Thread
                    </Link>
                </Button>
            </div>

            <!-- Search & Filter -->
            <div class="space-y-3">
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="search"
                        placeholder="Cari thread..."
                        class="pl-10"
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
            <div v-else class="text-center py-12">
                <MessageCircle class="mx-auto size-12 text-muted-foreground/50" />
                <h3 class="mt-4 text-lg font-medium">Belum ada diskusi</h3>
                <p class="mt-1 text-sm text-muted-foreground">Mulai diskusi pertama di forum!</p>
                <Button as-child class="mt-4">
                    <Link href="/forum/create">
                        <Plus class="mr-2 size-4" />
                        Buat Thread
                    </Link>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
