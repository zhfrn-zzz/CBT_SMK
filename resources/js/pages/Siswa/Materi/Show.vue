<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { CheckCircle2, ChevronLeft, ChevronRight, Download } from 'lucide-vue-next';
import { useYouTubeEmbed } from '@/composables/useYouTubeEmbed';
import type { BreadcrumbItem, Material, MaterialProgress } from '@/types';

const props = defineProps<{
    material: Material;
    progress: MaterialProgress | null;
    prev: { id: number; title: string } | null;
    next: { id: number; title: string } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Materi', href: '/siswa/materi' },
    { title: props.material.title, href: `/siswa/materi/${props.material.id}` },
];

const { embedUrl } = useYouTubeEmbed(props.material.video_url);

function markComplete() {
    router.post(`/siswa/materi/${props.material.id}/complete`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="material.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl p-4 space-y-4">
            <FlashMessage />

            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold">{{ material.title }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ material.subject?.name }}
                        <span v-if="material.topic"> · {{ material.topic }}</span>
                    </p>
                </div>
                <div v-if="progress?.is_completed" class="flex items-center gap-1 text-green-600 text-sm shrink-0">
                    <CheckCircle2 class="size-4" /> Selesai
                </div>
            </div>

            <p v-if="material.description" class="text-sm text-muted-foreground">{{ material.description }}</p>

            <!-- File -->
            <div v-if="material.type === 'file'" class="rounded-lg border p-6 text-center space-y-3">
                <p class="font-medium">{{ material.file_original_name }}</p>
                <p v-if="material.formatted_file_size" class="text-sm text-muted-foreground">{{ material.formatted_file_size }}</p>
                <Button as-child>
                    <a :href="`/siswa/materi/${material.id}/download`">
                        <Download class="size-4" />Download File
                    </a>
                </Button>
            </div>

            <!-- Video -->
            <div v-else-if="material.type === 'video_link' && embedUrl" class="aspect-video w-full overflow-hidden rounded-md border">
                <iframe :src="embedUrl" class="size-full" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen />
            </div>
            <div v-else-if="material.type === 'video_link'" class="rounded-lg border p-6 text-center text-muted-foreground space-y-2">
                <p>Video tidak tersedia.</p>
                <Button variant="outline" size="sm" as-child>
                    <a :href="material.video_url!" target="_blank">Buka di YouTube</a>
                </Button>
            </div>

            <!-- Text -->
            <div v-else-if="material.type === 'text'" class="rounded-lg border p-4 prose prose-sm max-w-none">
                <!-- eslint-disable-next-line vue/no-v-html -->
                <div v-html="material.text_content" />
            </div>

            <!-- Mark complete -->
            <div class="flex items-center justify-between border-t pt-4">
                <div class="flex gap-2">
                    <Button v-if="prev" variant="outline" size="sm" as-child>
                        <Link :href="`/siswa/materi/${prev.id}`">
                            <ChevronLeft class="size-4" />{{ prev.title }}
                        </Link>
                    </Button>
                </div>
                <Button v-if="!progress?.is_completed" @click="markComplete">
                    <CheckCircle2 class="size-4" />Tandai Selesai
                </Button>
                <Badge v-else variant="default" class="bg-green-600">
                    <CheckCircle2 class="size-3 mr-1" />Sudah Selesai
                </Badge>
                <div class="flex gap-2">
                    <Button v-if="next" variant="outline" size="sm" as-child>
                        <Link :href="`/siswa/materi/${next.id}`">
                            {{ next.title }}<ChevronRight class="size-4" />
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
