<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import QuestionForm from '@/components/Exam/QuestionForm.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { BreadcrumbItem, QuestionType, QuestionOptionForm } from '@/types';

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
});

function submit() {
    form.post(`/guru/bank-soal/${props.questionBank.id}/soal`);
}
</script>

<template>
    <Head title="Tambah Soal" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <Card class="max-w-3xl">
                <CardHeader>
                    <CardTitle>Tambah Soal</CardTitle>
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
