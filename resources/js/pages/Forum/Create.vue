<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { MessagesSquare } from 'lucide-vue-next';
import type { BreadcrumbItem, ForumCategory } from '@/types';

defineProps<{
    categories: ForumCategory[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Forum', href: '/forum' },
    { title: 'Buat Thread', href: '/forum/create' },
];

const form = useForm({
    title: '',
    content: '',
    forum_category_id: null as number | null,
});

function submit() {
    form.post('/forum');
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Buat Thread Baru" />

        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <PageHeader title="Buat Thread Baru" description="Mulai diskusi baru di forum" :icon="MessagesSquare" />

            <Card class="mx-auto w-full max-w-2xl">
                <CardContent class="p-6">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="title">Judul</Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                placeholder="Judul thread"
                                class="h-11"
                                :class="{ 'border-destructive': form.errors.title }"
                            />
                            <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="category">Kategori</Label>
                            <Select v-model="form.forum_category_id as any">
                                <SelectTrigger class="h-11">
                                    <SelectValue placeholder="Pilih kategori (opsional)" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="cat in categories" :key="cat.id" :value="cat.id as any">
                                        {{ cat.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label for="content">Konten</Label>
                            <Textarea
                                id="content"
                                v-model="form.content"
                                placeholder="Tulis konten thread..."
                                rows="8"
                                :class="{ 'border-destructive': form.errors.content }"
                            />
                            <p v-if="form.errors.content" class="text-sm text-destructive">{{ form.errors.content }}</p>
                        </div>

                        <div class="flex gap-3 pt-2">
                            <LoadingButton type="submit" :loading="form.processing">
                                Buat Thread
                            </LoadingButton>
                            <Button variant="outline" as-child>
                                <Link href="/forum">Batal</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
