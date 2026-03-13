<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Download, FileUp, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem, QuestionBank } from '@/types';
import { questionTypeLabels } from '@/types/exam';

const props = defineProps<{
    questionBank: QuestionBank;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Bank Soal', href: '/guru/bank-soal' },
    { title: props.questionBank.name, href: `/guru/bank-soal/${props.questionBank.id}` },
];

const importDialogOpen = ref(false);
const importForm = useForm({
    file: null as File | null,
});

function handleFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    importForm.file = target.files?.[0] ?? null;
}

function submitImport() {
    if (!importForm.file) return;

    importForm.post(`/guru/bank-soal/${props.questionBank.id}/soal/import`, {
        forceFormData: true,
        onSuccess: () => {
            importDialogOpen.value = false;
            importForm.reset();
        },
    });
}

function deleteQuestion(questionId: number) {
    router.delete(`/guru/bank-soal/${props.questionBank.id}/soal/${questionId}`, {
        preserveScroll: true,
    });
}

function stripHtml(html: string): string {
    const div = document.createElement('div');
    div.innerHTML = html;
    return div.textContent || div.innerText || '';
}
</script>

<template>
    <Head :title="questionBank.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />

            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-xl font-semibold">{{ questionBank.name }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ questionBank.subject?.name }} &middot;
                        {{ questionBank.questions?.length ?? 0 }} soal
                    </p>
                    <p v-if="questionBank.description" class="mt-1 text-sm text-muted-foreground">
                        {{ questionBank.description }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <!-- Import Dialog -->
                    <Dialog v-model:open="importDialogOpen">
                        <DialogTrigger as-child>
                            <Button variant="outline" size="sm">
                                <FileUp class="size-4" />
                                Import Soal
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Import Soal dari Excel</DialogTitle>
                                <DialogDescription>
                                    Upload file Excel sesuai template. Soal akan ditambahkan ke bank soal ini.
                                </DialogDescription>
                            </DialogHeader>
                            <div class="space-y-4 py-4">
                                <div>
                                    <Button variant="outline" size="sm" as-child>
                                        <a :href="`/guru/bank-soal/${questionBank.id}/soal/template-download`">
                                            <Download class="size-4" />
                                            Download Template
                                        </a>
                                    </Button>
                                </div>
                                <div class="space-y-2">
                                    <Label for="import-file">File Excel</Label>
                                    <Input
                                        id="import-file"
                                        type="file"
                                        accept=".xlsx,.xls,.csv"
                                        @change="handleFileChange"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Format: xlsx, xls, atau csv. Maksimal 5MB.
                                    </p>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button
                                    @click="submitImport"
                                    :disabled="!importForm.file || importForm.processing"
                                >
                                    Import
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <Button size="sm" as-child>
                        <Link :href="`/guru/bank-soal/${questionBank.id}/soal/create`">
                            <Plus class="size-4" />
                            Tambah Soal
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Questions List -->
            <div v-if="questionBank.questions && questionBank.questions.length > 0" class="space-y-3">
                <Card v-for="(question, index) in questionBank.questions" :key="question.id">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2">
                                <span class="flex size-7 items-center justify-center rounded-full bg-primary text-xs font-medium text-primary-foreground">
                                    {{ index + 1 }}
                                </span>
                                <Badge variant="secondary">
                                    {{ questionTypeLabels[question.type] }}
                                </Badge>
                                <Badge variant="outline">
                                    {{ question.points }} poin
                                </Badge>
                            </div>
                            <div class="flex gap-1">
                                <Button variant="ghost" size="icon-sm" as-child>
                                    <Link :href="`/guru/bank-soal/${questionBank.id}/soal/${question.id}/edit`">
                                        <Pencil class="size-4" />
                                    </Link>
                                </Button>
                                <AlertDialog>
                                    <AlertDialogTrigger as-child>
                                        <Button variant="ghost" size="icon-sm">
                                            <Trash2 class="size-4 text-destructive" />
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>Hapus Soal</AlertDialogTitle>
                                            <AlertDialogDescription>
                                                Apakah Anda yakin ingin menghapus soal nomor {{ index + 1 }}?
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>Batal</AlertDialogCancel>
                                            <AlertDialogAction
                                                class="bg-destructive text-white hover:bg-destructive/90"
                                                @click="deleteQuestion(question.id)"
                                            >
                                                Hapus
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <!-- Question Content -->
                        <div class="prose prose-sm dark:prose-invert max-w-none" v-html="question.content" />

                        <!-- Media -->
                        <img
                            v-if="question.media_url"
                            :src="question.media_url"
                            alt="Media soal"
                            class="mt-3 max-h-48 rounded-md border"
                        />

                        <!-- Options -->
                        <div
                            v-if="question.options && question.options.length > 0"
                            class="mt-3 space-y-1.5"
                        >
                            <div
                                v-for="option in question.options"
                                :key="option.id"
                                class="flex items-start gap-2 rounded-md px-3 py-1.5 text-sm"
                                :class="option.is_correct ? 'bg-green-50 dark:bg-green-950/30 text-green-700 dark:text-green-400' : ''"
                            >
                                <span class="font-medium">{{ option.label }}.</span>
                                <span>{{ option.content }}</span>
                                <Badge v-if="option.is_correct" variant="default" class="ml-auto text-xs">
                                    Benar
                                </Badge>
                            </div>
                        </div>

                        <!-- Explanation -->
                        <div v-if="question.explanation" class="mt-3 rounded-md bg-muted p-3 text-sm">
                            <span class="font-medium">Pembahasan:</span>
                            {{ question.explanation }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty State -->
            <Card v-else>
                <CardContent class="flex flex-col items-center justify-center py-12 text-center">
                    <p class="text-muted-foreground">Belum ada soal di bank soal ini.</p>
                    <div class="mt-4 flex gap-2">
                        <Button size="sm" as-child>
                            <Link :href="`/guru/bank-soal/${questionBank.id}/soal/create`">
                                <Plus class="size-4" />
                                Tambah Soal
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" @click="importDialogOpen = true">
                            <FileUp class="size-4" />
                            Import dari Excel
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
