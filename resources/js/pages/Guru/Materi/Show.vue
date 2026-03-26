<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { BookOpen, CheckCircle2, Download, Pencil, Trash2, XCircle } from 'lucide-vue-next';
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

function deleteMaterial() {
    router.delete(`/guru/materi/${props.material.id}`);
}
</script>

<template>
    <Head :title="material.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <PageHeader :title="material.title" :icon="BookOpen">
                <template #actions>
                    <Badge v-if="!material.is_published" variant="secondary">Draft</Badge>
                    <Button variant="outline" size="sm" as-child v-if="material.type === 'file'">
                        <a :href="`/guru/materi/${material.id}/download`">
                            <Download class="size-4" />
                            <span class="hidden sm:inline">Download</span>
                        </a>
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/guru/materi/${material.id}/edit`">
                            <Pencil class="size-4" />
                            <span class="hidden sm:inline">Edit</span>
                        </Link>
                    </Button>
                    <ConfirmDialog
                        title="Hapus Materi?"
                        description="Materi dan semua data progress siswa akan dihapus permanen. Lanjutkan?"
                        confirm-label="Ya, Hapus"
                        @confirm="deleteMaterial"
                    >
                        <Button variant="destructive" size="sm">
                            <Trash2 class="size-4" />
                            <span class="hidden sm:inline">Hapus</span>
                        </Button>
                    </ConfirmDialog>
                </template>
            </PageHeader>

            <div class="text-sm text-muted-foreground">
                <p v-if="material.description" class="mb-1">{{ material.description }}</p>
                <p>
                    {{ material.subject?.name }} · {{ material.classroom?.name }}
                    <span v-if="material.topic"> · {{ material.topic }}</span>
                </p>
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

                <div class="overflow-x-auto rounded-lg border bg-card">
                    <Table class="min-w-[500px]">
                        <TableHeader>
                            <TableRow class="bg-slate-50">
                                <TableHead class="text-xs font-semibold uppercase tracking-wider">Nama Siswa</TableHead>
                                <TableHead class="text-xs font-semibold uppercase tracking-wider">Username</TableHead>
                                <TableHead class="text-center text-xs font-semibold uppercase tracking-wider">Status</TableHead>
                                <TableHead class="text-xs font-semibold uppercase tracking-wider">Tanggal Selesai</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="s in students" :key="s.id" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
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
