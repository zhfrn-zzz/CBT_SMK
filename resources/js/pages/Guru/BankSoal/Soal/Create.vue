<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import QuestionForm from '@/components/Exam/QuestionForm.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { FileQuestion } from 'lucide-vue-next';
import type { BreadcrumbItem, QuestionType, QuestionOptionForm, MatchingPairForm } from '@/types';

const props = defineProps<{
    questionBank: { id: number; name: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Bank Soal', href: '/guru/bank-soal' },
    { title: props.questionBank.name, href: `/guru/bank-soal/${props.questionBank.id}` },
    { title: 'Tambah Soal', href: `/guru/bank-soal/${props.questionBank.id}/soal/create` },
];

const form = useForm<{
    type: QuestionType;
    content: string;
    points: number;
    explanation: string;
    options: QuestionOptionForm[];
    keywords: string[];
    matching_pairs: MatchingPairForm[];
}>({
    type: 'pilihan_ganda',
    content: '',
    points: 1,
    explanation: '',
    options: [
        { label: 'A', content: '', is_correct: false },
        { label: 'B', content: '', is_correct: false },
        { label: 'C', content: '', is_correct: false },
        { label: 'D', content: '', is_correct: false },
    ],
    keywords: [],
    matching_pairs: [],
});

function submit() {
    form.post(`/guru/bank-soal/${props.questionBank.id}/soal`);
}
</script>

<template>
    <Head title="Tambah Soal" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-lg p-4">
            <PageHeader title="Tambah Soal" :icon="FileQuestion" />
            <Card class="max-w-3xl">
                <CardContent class="p-6">
                    <QuestionForm :form="form" @submit="submit">
                        <template #actions>
                            <div class="flex items-center justify-end gap-3 pt-4">
                                <Button variant="outline" as-child>
                                    <Link :href="`/guru/bank-soal/${questionBank.id}`">Batal</Link>
                                </Button>
                                <LoadingButton type="submit" :loading="form.processing" :disabled="form.processing">Simpan</LoadingButton>
                            </div>
                        </template>
                    </QuestionForm>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
