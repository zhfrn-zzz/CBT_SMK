<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { CheckCircle2, Download, Pencil, XCircle } from 'lucide-vue-next';
import { useYouTubeEmbed } from '@/composables/useYouTubeEmbed';
import type { BreadcrumbItem, Material } from '@/types';

interface StudentProgress {
    id: number;
    name: string;
    username: string;
    is_completed: boolean;
    completed_at: string | null;
}

const props = defineProps<{
    material: Material;
    students: StudentProgress[];
    completion_count: number;
    total_students: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Materi', href: '/guru/materi' },
    { title: props.material.title, href: `/guru/materi/${props.material.id}` },
];

const activeTab = ref<'content' | 'progress'>('content');

const { embedUrl } = useYouTubeEmbed(props.material.video_url);

function formatDate(date: string | null) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head :title="material.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />

            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-semibold">{{ material.title }}</h2>
                        <Badge v-if="!material.is_published" variant="secondary">Draft</Badge>
                    </div>
                    <p v-if="material.description" class="mt-1 text-sm text-muted-foreground">{{ material.description }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ material.subject?.name }} · {{ material.classroom?.name }}
                        <span v-if="material.topic"> · {{ material.topic }}</span>
                    </p>
                </div>
                <div class="flex gap-2 shrink-0">
                    <Button variant="outline" size="sm" as-child v-if="material.type === 'file'">
                        <a :href="`/guru/materi/${material.id}/download`">
                            <Download class="size-4" />
                            Download
                        </a>
                    </Button>
                    <Button size="sm" as-child>
                        <Link :href="`/guru/materi/${material.id}/edit`">
                            <Pencil class="size-4" />
                            Edit
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Tab -->
            <div class="flex gap-1 border-b">
                <button
                    v-for="tab in (['content', 'progress'] as const)"
                    :key="tab"
                    class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                    :class="activeTab === tab ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
                    @click="activeTab = tab"
                >
                    {{ tab === 'content' ? 'Konten' : 'Progress Siswa' }}
                </button>
            </div>

            <!-- Content Tab -->
            <div v-if="activeTab === 'content'">
                <!-- File type -->
                <div v-if="material.type === 'file'" class="rounded-lg border p-4 text-center">
                    <p class="font-medium">{{ material.file_original_name }}</p>
                    <p v-if="material.formatted_file_size" class="text-sm text-muted-foreground">{{ material.formatted_file_size }}</p>
                    <Button class="mt-3" as-child>
                        <a :href="`/guru/materi/${material.id}/download`">
                            <Download class="size-4" />
                            Download File
                        </a>
                    </Button>
                </div>

                <!-- Video type -->
                <div v-else-if="material.type === 'video_link' && embedUrl" class="aspect-video w-full overflow-hidden rounded-md border">
                    <iframe :src="embedUrl" class="size-full" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen />
                </div>
                <div v-else-if="material.type === 'video_link'" class="rounded-lg border p-4 text-center text-muted-foreground">
                    Video tidak tersedia.
                </div>

                <!-- Text type -->
                <div v-else-if="material.type === 'text'" class="rounded-lg border p-4 prose prose-sm max-w-none">
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <div v-html="material.text_content" />
                </div>
            </div>

            <!-- Progress Tab -->
            <div v-if="activeTab === 'progress'" class="space-y-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm">Ringkasan Progress</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">{{ completion_count }} / {{ total_students }}</p>
                        <p class="text-sm text-muted-foreground">siswa sudah menyelesaikan ({{ total_students > 0 ? Math.round(completion_count / total_students * 100) : 0 }}%)</p>
                        <div class="mt-2 h-2 rounded-full bg-muted overflow-hidden">
                            <div class="h-full bg-primary rounded-full transition-all" :style="{ width: total_students > 0 ? `${completion_count / total_students * 100}%` : '0%' }" />
                        </div>
                    </CardContent>
                </Card>

                <div class="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama Siswa</TableHead>
                                <TableHead>Username</TableHead>
                                <TableHead class="text-center">Status</TableHead>
                                <TableHead>Tanggal Selesai</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="s in students" :key="s.id">
                                <TableCell class="font-medium">{{ s.name }}</TableCell>
                                <TableCell class="text-muted-foreground">{{ s.username }}</TableCell>
                                <TableCell class="text-center">
                                    <span v-if="s.is_completed" class="inline-flex items-center gap-1 text-green-600">
                                        <CheckCircle2 class="size-4" /> Selesai
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 text-muted-foreground">
                                        <XCircle class="size-4" /> Belum
                                    </span>
                                </TableCell>
                                <TableCell>{{ formatDate(s.completed_at) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
