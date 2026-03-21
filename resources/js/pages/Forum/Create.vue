<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
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

        <div class="mx-auto max-w-2xl space-y-6 p-6">
            <h1 class="text-2xl font-bold">Buat Thread Baru</h1>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="title">Judul</Label>
                    <Input
                        id="title"
                        v-model="form.title"
                        placeholder="Judul thread"
                        :class="{ 'border-destructive': form.errors.title }"
                    />
                    <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="category">Kategori</Label>
                    <Select v-model="form.forum_category_id as any">
                        <SelectTrigger>
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

                <div class="flex gap-3">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Menyimpan...' : 'Buat Thread' }}
                    </Button>
                    <Button variant="outline" as-child>
                        <Link href="/forum">Batal</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
