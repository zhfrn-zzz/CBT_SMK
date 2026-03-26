<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { BookOpen, Clock, Play, CheckCircle2, ClipboardList } from 'lucide-vue-next';
import type { BreadcrumbItem, SiswaExamListItem } from '@/types';

const props = defineProps<{
    examSessions: SiswaExamListItem[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/siswa/dashboard' },
    { title: 'Ujian', href: '/siswa/ujian' },
];

const now = Date.now();

const upcoming = computed(() =>
    props.examSessions.filter(e =>
        e.status === 'scheduled' || (e.status === 'active' && !e.attempt_status)
    )
);

const inProgress = computed(() =>
    props.examSessions.filter(e => e.attempt_status === 'in_progress')
);

const completed = computed(() =>
    props.examSessions.filter(e =>
        e.attempt_status === 'submitted' || e.attempt_status === 'graded'
    )
);

function formatDate(date: string) {
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function isExamActive(exam: SiswaExamListItem): boolean {
    return exam.status === 'active' && new Date(exam.starts_at).getTime() <= now && new Date(exam.ends_at).getTime() >= now;
}
</script>

<template>
    <Head title="Ujian" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <FlashMessage />

            <PageHeader title="Ujian" description="Daftar ujian Anda" :icon="ClipboardList" />

            <Tabs default-value="upcoming">
                <TabsList>
                    <TabsTrigger value="upcoming">
                        Tersedia ({{ upcoming.length }})
                    </TabsTrigger>
                    <TabsTrigger value="in_progress">
                        Berlangsung ({{ inProgress.length }})
                    </TabsTrigger>
                    <TabsTrigger value="completed">
                        Selesai ({{ completed.length }})
                    </TabsTrigger>
                </TabsList>

                <!-- Upcoming -->
                <TabsContent value="upcoming" class="mt-4">
                    <EmptyState v-if="upcoming.length === 0" :icon="ClipboardList" title="Tidak ada ujian tersedia" description="Belum ada ujian yang dijadwalkan untuk Anda saat ini." />
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Card v-for="exam in upcoming" :key="exam.id">
                            <CardHeader class="pb-3">
                                <div class="flex items-start justify-between">
                                    <CardTitle class="text-base">{{ exam.name }}</CardTitle>
                                    <div class="flex gap-1">
                                        <Badge v-if="exam.is_remedial" variant="destructive">Remedial</Badge>
                                        <Badge :variant="isExamActive(exam) ? 'default' : 'outline'">
                                            {{ isExamActive(exam) ? 'Aktif' : 'Dijadwalkan' }}
                                        </Badge>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <BookOpen class="size-4" />
                                    {{ exam.subject }}
                                </div>
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Clock class="size-4" />
                                    {{ exam.duration_minutes }} menit
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ formatDate(exam.starts_at) }} - {{ formatDate(exam.ends_at) }}
                                </div>
                                <Button
                                    v-if="isExamActive(exam)"
                                    class="w-full"
                                    as-child
                                >
                                    <Link :href="`/siswa/ujian/${exam.id}/verify-token`">
                                        <Play class="size-4" />
                                        Mulai Ujian
                                    </Link>
                                </Button>
                                <Button v-else variant="outline" class="w-full" disabled>
                                    Belum Dimulai
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <!-- In Progress -->
                <TabsContent value="in_progress" class="mt-4">
                    <EmptyState v-if="inProgress.length === 0" :icon="ClipboardList" title="Tidak ada ujian berlangsung" description="Tidak ada ujian yang sedang Anda kerjakan." />
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Card v-for="exam in inProgress" :key="exam.id" class="border-primary">
                            <CardHeader class="pb-3">
                                <div class="flex items-start justify-between">
                                    <CardTitle class="text-base">{{ exam.name }}</CardTitle>
                                    <Badge variant="default">Sedang Mengerjakan</Badge>
                                </div>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <BookOpen class="size-4" />
                                    {{ exam.subject }}
                                </div>
                                <Button class="w-full" as-child>
                                    <Link :href="`/siswa/ujian/${exam.id}/exam`">
                                        Lanjutkan Ujian
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <!-- Completed -->
                <TabsContent value="completed" class="mt-4">
                    <EmptyState v-if="completed.length === 0" :icon="ClipboardList" title="Belum ada riwayat ujian" description="Belum ada ujian yang telah Anda selesaikan." />
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Card v-for="exam in completed" :key="exam.id">
                            <CardHeader class="pb-3">
                                <div class="flex items-start justify-between">
                                    <CardTitle class="text-base">{{ exam.name }}</CardTitle>
                                    <div class="flex gap-1">
                                        <Badge v-if="exam.is_remedial" variant="destructive">Remedial</Badge>
                                        <Badge variant="secondary">
                                            <CheckCircle2 class="mr-1 size-3" />
                                            {{ exam.attempt_status_label }}
                                        </Badge>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <BookOpen class="size-4" />
                                    {{ exam.subject }}
                                </div>
                                <div v-if="exam.is_published && exam.score !== null" class="text-center">
                                    <p class="text-3xl font-bold" :class="exam.score >= 70 ? 'text-green-600' : 'text-red-600'">
                                        {{ exam.score }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">Nilai</p>
                                </div>
                                <div v-else class="py-2 text-center text-sm text-muted-foreground">
                                    Nilai belum dipublikasikan
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
