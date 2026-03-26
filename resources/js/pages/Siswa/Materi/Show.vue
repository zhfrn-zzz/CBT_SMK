<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import LoadingButton from '@/Components/LoadingButton.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { BookOpen, CheckCircle2, ChevronLeft, ChevronRight, Download } from 'lucide-vue-next';
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

const markingComplete = ref(false);
function markComplete() {
    markingComplete.value = true;
    router.post(`/siswa/materi/${props.material.id}/complete`, {}, {
        preserveScroll: true,
        onFinish: () => { markingComplete.value = false; },
    });
}
</script>

<template>
    <Head :title="material.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl p-4 space-y-4">
            <FlashMessage />

            <PageHeader :title="material.title" :icon="BookOpen">
                <template #actions>
                    <div v-if="progress?.is_completed" class="flex items-center gap-1 text-green-600 text-sm">
                        <CheckCircle2 class="size-4" /> Selesai
                    </div>
                </template>
            </PageHeader>
            <p class="text-sm text-muted-foreground">
                {{ material.subject?.name }}
                <span v-if="material.topic"> · {{ material.topic }}</span>
            </p>

            <p v-if="material.description" class="text-sm text-muted-foreground">{{ material.description }}</p>

            <!-- File -->
            <Card v-if="material.type === 'file'">
                <CardContent class="p-6 text-center space-y-3">
                    <p class="font-medium">{{ material.file_original_name }}</p>
                    <p v-if="material.formatted_file_size" class="text-sm text-muted-foreground">{{ material.formatted_file_size }}</p>
                    <Button as-child>
                        <a :href="`/siswa/materi/${material.id}/download`">
                            <Download class="size-4" />Download File
                        </a>
                    </Button>
                </CardContent>
            </Card>

            <!-- Video -->
            <Card v-else-if="material.type === 'video_link' && embedUrl" class="overflow-hidden">
                <CardContent class="p-0">
                    <div class="aspect-video w-full">
                        <iframe :src="embedUrl" class="size-full" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen />
                    </div>
                </CardContent>
            </Card>
            <Card v-else-if="material.type === 'video_link'">
                <CardContent class="p-6 text-center text-muted-foreground space-y-2">
                    <p>Video tidak tersedia.</p>
                    <Button variant="outline" size="sm" as-child>
                        <a :href="material.video_url!" target="_blank">Buka di YouTube</a>
                    </Button>
                </CardContent>
            </Card>

            <!-- Text -->
            <Card v-else-if="material.type === 'text'">
                <CardContent class="p-4 prose prose-sm max-w-none">
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <div v-html="material.text_content" />
                </CardContent>
            </Card>

            <!-- Mark complete -->
            <div class="flex flex-wrap items-center justify-between gap-2 border-t pt-4">
                <div class="flex gap-2">
                    <Button v-if="prev" variant="outline" size="sm" as-child>
                        <Link :href="`/siswa/materi/${prev.id}`">
                            <ChevronLeft class="size-4" />{{ prev.title }}
                        </Link>
                    </Button>
                </div>
                <LoadingButton v-if="!progress?.is_completed" :loading="markingComplete" @click="markComplete">
                    <CheckCircle2 class="size-4" />Tandai Selesai
                </LoadingButton>
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
