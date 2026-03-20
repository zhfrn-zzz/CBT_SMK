<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import type { AcademicYear, BreadcrumbItem, ExamSession, QuestionBank, Subject } from '@/types';

const props = defineProps<{
    examSession: ExamSession;
    subjects: Pick<Subject, 'id' | 'name' | 'code'>[];
    questionBanks: (QuestionBank & { questions_count?: number })[];
    academicYears: AcademicYear[];
    classrooms: { id: number; name: string; department: string | null }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Ujian', href: '/guru/ujian' },
    { title: 'Edit', href: '#' },
];

function formatDateForInput(dateStr: string): string {
    const d = new Date(dateStr);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

const form = ref({
    name: props.examSession.name,
    subject_id: String(props.examSession.subject_id),
    academic_year_id: String(props.examSession.academic_year_id),
    question_bank_id: String(props.examSession.question_bank_id),
    duration_minutes: props.examSession.duration_minutes,
    starts_at: formatDateForInput(props.examSession.starts_at),
    ends_at: formatDateForInput(props.examSession.ends_at),
    is_randomize_questions: props.examSession.is_randomize_questions,
    is_randomize_options: props.examSession.is_randomize_options,
    pool_count: props.examSession.pool_count ? String(props.examSession.pool_count) : '',
    kkm: props.examSession.kkm ? String(props.examSession.kkm) : '',
    max_tab_switches: props.examSession.max_tab_switches !== null ? String(props.examSession.max_tab_switches) : '',
    classroom_ids: props.examSession.classrooms?.map(c => c.id) ?? [],
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);

const filteredBanks = computed(() => {
    if (!form.value.subject_id) return props.questionBanks;
    return props.questionBanks.filter(b => b.subject_id === Number(form.value.subject_id));
});

function toggleClassroom(id: number, checked: boolean) {
    if (checked) {
        if (!form.value.classroom_ids.includes(id)) {
            form.value.classroom_ids.push(id);
        }
    } else {
        const idx = form.value.classroom_ids.indexOf(id);
        if (idx !== -1) {
            form.value.classroom_ids.splice(idx, 1);
        }
    }
}

function submit() {
    processing.value = true;
    router.put(`/guru/ujian/${props.examSession.id}`, {
        ...form.value,
        pool_count: form.value.pool_count || null,
        kkm: form.value.kkm || null,
        max_tab_switches: form.value.max_tab_switches || null,
    }, {
        onError: (e) => { errors.value = e; },
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <Head title="Edit Sesi Ujian" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h2 class="text-xl font-semibold">Edit Sesi Ujian</h2>

            <form class="max-w-2xl space-y-6" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="name">Nama Ujian</Label>
                    <Input id="name" v-model="form.name" />
                    <InputError :message="errors.name" />
                </div>

                <div class="space-y-2">
                    <Label>Mata Pelajaran</Label>
                    <Select v-model="form.subject_id">
                        <SelectTrigger>
                            <SelectValue placeholder="Pilih Mata Pelajaran" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="s in subjects" :key="s.id" :value="String(s.id)">
                                {{ s.name }} ({{ s.code }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.subject_id" />
                </div>

                <div class="space-y-2">
                    <Label>Tahun Ajaran</Label>
                    <Select v-model="form.academic_year_id">
                        <SelectTrigger>
                            <SelectValue placeholder="Pilih Tahun Ajaran" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="ay in academicYears" :key="ay.id" :value="String(ay.id)">
                                {{ ay.name }} - {{ ay.semester === 'ganjil' ? 'Ganjil' : 'Genap' }}
                                {{ ay.is_active ? '(Aktif)' : '' }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.academic_year_id" />
                </div>

                <div class="space-y-2">
                    <Label>Bank Soal</Label>
                    <Select v-model="form.question_bank_id">
                        <SelectTrigger>
                            <SelectValue placeholder="Pilih Bank Soal" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="b in filteredBanks" :key="b.id" :value="String(b.id)">
                                {{ b.name }} ({{ b.questions_count ?? 0 }} soal)
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.question_bank_id" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="duration">Durasi (menit)</Label>
                        <Input id="duration" v-model.number="form.duration_minutes" type="number" min="1" max="480" />
                        <InputError :message="errors.duration_minutes" />
                    </div>
                    <div class="space-y-2">
                        <Label for="starts_at">Waktu Mulai</Label>
                        <Input id="starts_at" v-model="form.starts_at" type="datetime-local" />
                        <InputError :message="errors.starts_at" />
                    </div>
                    <div class="space-y-2">
                        <Label for="ends_at">Waktu Selesai</Label>
                        <Input id="ends_at" v-model="form.ends_at" type="datetime-local" />
                        <InputError :message="errors.ends_at" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="pool_count">Jumlah Soal (pool)</Label>
                        <Input id="pool_count" v-model="form.pool_count" type="number" min="1" placeholder="Semua soal" />
                    </div>
                    <div class="space-y-2">
                        <Label for="kkm">KKM</Label>
                        <Input id="kkm" v-model="form.kkm" type="number" min="0" max="100" placeholder="Opsional" />
                    </div>
                    <div class="space-y-2">
                        <Label for="max_tab_switches">Maks. Pindah Tab</Label>
                        <Input id="max_tab_switches" v-model="form.max_tab_switches" type="number" min="0" placeholder="Unlimited" />
                    </div>
                </div>

                <div class="flex gap-6">
                    <label class="flex items-center gap-2">
                        <Checkbox v-model="form.is_randomize_questions" />
                        <span class="text-sm">Acak urutan soal</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <Checkbox v-model="form.is_randomize_options" />
                        <span class="text-sm">Acak urutan pilihan jawaban</span>
                    </label>
                </div>

                <div class="space-y-2">
                    <Label>Kelas Peserta</Label>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <label
                            v-for="c in classrooms"
                            :key="c.id"
                            class="flex items-center gap-2 rounded-md border p-2 cursor-pointer hover:bg-muted/50"
                            :class="{ 'border-primary bg-primary/5': form.classroom_ids.includes(c.id) }"
                        >
                            <Checkbox
                                :model-value="form.classroom_ids.includes(c.id)"
                                @update:model-value="(val) => toggleClassroom(c.id, val as boolean)"
                            />
                            <div class="text-sm">
                                <p class="font-medium">{{ c.name }}</p>
                                <p v-if="c.department" class="text-xs text-muted-foreground">{{ c.department }}</p>
                            </div>
                        </label>
                    </div>
                    <InputError :message="errors.classroom_ids" />
                </div>

                <div class="flex gap-3">
                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </Button>
                    <Button variant="outline" type="button" as-child>
                        <a :href="`/guru/ujian/${examSession.id}`">Batal</a>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
