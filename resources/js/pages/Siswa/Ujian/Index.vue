<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { BookOpen, Clock, Play, CheckCircle2 } from 'lucide-vue-next';
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
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />

            <h2 class="text-xl font-semibold">Daftar Ujian</h2>

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
                    <div v-if="upcoming.length === 0" class="py-8 text-center text-muted-foreground">
                        Tidak ada ujian yang tersedia.
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Card v-for="exam in upcoming" :key="exam.id">
                            <CardHeader class="pb-3">
                                <div class="flex items-start justify-between">
                                    <CardTitle class="text-base">{{ exam.name }}</CardTitle>
                                    <Badge :variant="isExamActive(exam) ? 'default' : 'outline'">
                                        {{ isExamActive(exam) ? 'Aktif' : 'Dijadwalkan' }}
                                    </Badge>
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
                    <div v-if="inProgress.length === 0" class="py-8 text-center text-muted-foreground">
                        Tidak ada ujian yang sedang berlangsung.
                    </div>
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
                    <div v-if="completed.length === 0" class="py-8 text-center text-muted-foreground">
                        Belum ada ujian yang diselesaikan.
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Card v-for="exam in completed" :key="exam.id">
                            <CardHeader class="pb-3">
                                <div class="flex items-start justify-between">
                                    <CardTitle class="text-base">{{ exam.name }}</CardTitle>
                                    <Badge variant="secondary">
                                        <CheckCircle2 class="mr-1 size-3" />
                                        {{ exam.attempt_status_label }}
                                    </Badge>
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
