<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Pencil, Trash2, Plus } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import type { CompetencyStandard } from '@/types/analytics';
import { ref } from 'vue';

const props = defineProps<{
    questionBank: { id: number; name: string; subject_id: number; subject_name: string };
    competencies: CompetencyStandard[];
    questions: Array<{
        id: number;
        content_preview: string;
        type: string;
        competency_standard_ids: number[];
    }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Bank Soal', href: '/guru/bank-soal' },
    { title: props.questionBank.name, href: `/guru/bank-soal/${props.questionBank.id}` },
    { title: 'Kompetensi Dasar', href: '#' },
];

const editingId = ref<number | null>(null);

const createForm = useForm({ code: '', name: '', description: '' });
const editForm = useForm({ code: '', name: '', description: '' });

function startEdit(comp: CompetencyStandard) {
    editingId.value = comp.id;
    editForm.code = comp.code;
    editForm.name = comp.name;
    editForm.description = comp.description ?? '';
}

function cancelEdit() {
    editingId.value = null;
    editForm.reset();
}

function submitCreate() {
    createForm.post(`/guru/bank-soal/${props.questionBank.id}/kompetensi`, {
        onSuccess: () => createForm.reset(),
    });
}

function submitEdit(id: number) {
    editForm.put(`/guru/bank-soal/${props.questionBank.id}/kompetensi/${id}`, {
        onSuccess: () => {
            editingId.value = null;
            editForm.reset();
        },
    });
}

function deleteComp(id: number) {
    if (confirm('Hapus kompetensi dasar ini?')) {
        router.delete(`/guru/bank-soal/${props.questionBank.id}/kompetensi/${id}`);
    }
}

const tagForms = ref<Record<number, number[]>>({});
props.questions.forEach(q => {
    tagForms.value[q.id] = [...q.competency_standard_ids];
});

function toggleTag(questionId: number, compId: number) {
    const arr = tagForms.value[questionId];
    const idx = arr.indexOf(compId);
    if (idx >= 0) {
        arr.splice(idx, 1);
    } else {
        arr.push(compId);
    }
}

function saveTag(questionId: number) {
    router.post(`/guru/bank-soal/${props.questionBank.id}/soal/${questionId}/tag-kompetensi`, {
        competency_standard_ids: tagForms.value[questionId],
    });
}
</script>

<template>
    <Head :title="`KD - ${questionBank.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <FlashMessage />
            <h1 class="text-2xl font-bold">Manajemen Kompetensi Dasar</h1>
            <p class="text-muted-foreground">{{ questionBank.subject_name }}</p>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Left: KD Management -->
                <div class="flex flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Plus class="h-4 w-4" /> Tambah KD
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form @submit.prevent="submitCreate" class="flex flex-col gap-3">
                                <div>
                                    <Label>Kode</Label>
                                    <Input v-model="createForm.code" placeholder="KD 3.1" />
                                </div>
                                <div>
                                    <Label>Nama Kompetensi</Label>
                                    <Input v-model="createForm.name" placeholder="Nama kompetensi dasar" />
                                </div>
                                <div>
                                    <Label>Deskripsi (opsional)</Label>
                                    <Textarea v-model="createForm.description" :rows="2" />
                                </div>
                                <Button type="submit" :disabled="createForm.processing">Tambah KD</Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Daftar KD</CardTitle></CardHeader>
                        <CardContent>
                            <div v-if="competencies.length === 0" class="py-4 text-center text-muted-foreground">
                                Belum ada KD untuk mata pelajaran ini.
                            </div>
                            <div v-for="comp in competencies" :key="comp.id" class="mb-2 rounded border p-3">
                                <div v-if="editingId !== comp.id">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <Badge variant="outline" class="text-xs">{{ comp.code }}</Badge>
                                            <p class="mt-1 font-medium">{{ comp.name }}</p>
                                            <p v-if="comp.description" class="mt-1 text-xs text-muted-foreground">{{ comp.description }}</p>
                                        </div>
                                        <div class="flex gap-1">
                                            <Button size="icon" variant="ghost" @click="startEdit(comp)">
                                                <Pencil class="h-4 w-4" />
                                            </Button>
                                            <Button size="icon" variant="ghost" @click="deleteComp(comp.id)">
                                                <Trash2 class="h-4 w-4 text-destructive" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                                <div v-else>
                                    <form @submit.prevent="submitEdit(comp.id)" class="flex flex-col gap-2">
                                        <Input v-model="editForm.code" placeholder="Kode" />
                                        <Input v-model="editForm.name" placeholder="Nama" />
                                        <Textarea v-model="editForm.description" :rows="2" placeholder="Deskripsi" />
                                        <div class="flex gap-2">
                                            <Button type="submit" size="sm" :disabled="editForm.processing">Simpan</Button>
                                            <Button type="button" size="sm" variant="outline" @click="cancelEdit">Batal</Button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right: Tag questions with KDs -->
                <Card>
                    <CardHeader><CardTitle>Tag Soal ke KD</CardTitle></CardHeader>
                    <CardContent>
                        <div v-if="questions.length === 0" class="py-4 text-center text-muted-foreground">
                            Tidak ada soal di bank soal ini.
                        </div>
                        <div v-if="competencies.length === 0" class="py-4 text-center text-muted-foreground">
                            Tambahkan KD terlebih dahulu.
                        </div>
                        <div v-for="q in questions" :key="q.id" class="mb-2 rounded border p-3">
                            <p class="mb-2 line-clamp-2 text-sm font-medium">{{ q.content_preview }}</p>
                            <div class="mb-2 flex flex-wrap gap-2">
                                <div v-for="comp in competencies" :key="comp.id" class="flex items-center gap-1">
                                    <Checkbox
                                        :id="`q${q.id}-c${comp.id}`"
                                        :model-value="tagForms[q.id]?.includes(comp.id)"
                                        @update:model-value="toggleTag(q.id, comp.id)"
                                    />
                                    <Label :for="`q${q.id}-c${comp.id}`" class="cursor-pointer text-xs">{{ comp.code }}</Label>
                                </div>
                            </div>
                            <Button size="sm" variant="outline" @click="saveTag(q.id)">Simpan Tag</Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
