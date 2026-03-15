<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import QuestionForm from '@/components/Exam/QuestionForm.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { BreadcrumbItem, Question, QuestionType, QuestionOptionForm, MatchingPairForm } from '@/types';

const props = defineProps<{
    questionBank: { id: number; name: string };
    question: Question;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Bank Soal', href: '/guru/bank-soal' },
    { title: props.questionBank.name, href: `/guru/bank-soal/${props.questionBank.id}` },
    { title: 'Edit Soal', href: `/guru/bank-soal/${props.questionBank.id}/soal/${props.question.id}/edit` },
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
    type: props.question.type,
    content: props.question.content,
    points: props.question.points,
    explanation: props.question.explanation ?? '',
    options: props.question.options?.map((opt) => ({
        label: opt.label,
        content: opt.content,
        is_correct: opt.is_correct,
    })) ?? [],
    keywords: props.question.keywords?.map((k) => k.keyword) ?? [],
    matching_pairs: props.question.matching_pairs?.map((p) => ({
        premise: p.premise,
        response: p.response,
    })) ?? [],
});

function submit() {
    form.put(`/guru/bank-soal/${props.questionBank.id}/soal/${props.question.id}`);
}
</script>

<template>
    <Head title="Edit Soal" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <Card class="max-w-3xl">
                <CardHeader>
                    <CardTitle>Edit Soal</CardTitle>
                </CardHeader>
                <CardContent>
                    <QuestionForm :form="form" @submit="submit">
                        <template #actions>
                            <div class="flex gap-2 pt-2">
                                <Button type="submit" :disabled="form.processing">Simpan</Button>
                                <Button variant="outline" as-child>
                                    <Link :href="`/guru/bank-soal/${questionBank.id}`">Batal</Link>
                                </Button>
                            </div>
                        </template>
                    </QuestionForm>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
