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
import type { AcademicYear, BreadcrumbItem, QuestionBank, Subject } from '@/types';

const props = defineProps<{
    subjects: Pick<Subject, 'id' | 'name' | 'code'>[];
    questionBanks: (QuestionBank & { questions_count?: number })[];
    academicYears: AcademicYear[];
    classrooms: { id: number; name: string; department: string | null }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Ujian', href: '/guru/ujian' },
    { title: 'Buat Ujian', href: '/guru/ujian/create' },
];

const form = ref({
    name: '',
    subject_id: '',
    academic_year_id: '',
    question_bank_id: '',
    duration_minutes: 60,
    starts_at: '',
    ends_at: '',
    is_randomize_questions: false,
    is_randomize_options: false,
    pool_count: '',
    kkm: '',
    max_tab_switches: '',
    classroom_ids: [] as number[],
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);

const filteredBanks = computed(() => {
    if (!form.value.subject_id) return props.questionBanks;
    return props.questionBanks.filter(b => b.subject_id === Number(form.value.subject_id));
});

const selectedBank = computed(() => {
    if (!form.value.question_bank_id) return null;
    return props.questionBanks.find(b => b.id === Number(form.value.question_bank_id));
});

function toggleClassroom(id: number) {
    const idx = form.value.classroom_ids.indexOf(id);
    if (idx === -1) {
        form.value.classroom_ids.push(id);
    } else {
        form.value.classroom_ids.splice(idx, 1);
    }
}

function submit() {
    processing.value = true;
    router.post('/guru/ujian', {
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
    <Head title="Buat Sesi Ujian" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h2 class="text-xl font-semibold">Buat Sesi Ujian</h2>

            <form class="max-w-2xl space-y-6" @submit.prevent="submit">
                <!-- Nama Ujian -->
                <div class="space-y-2">
                    <Label for="name">Nama Ujian</Label>
                    <Input id="name" v-model="form.name" placeholder="UTS ASJ XI TKJ Sesi 1" />
                    <InputError :message="errors.name" />
                </div>

                <!-- Mata Pelajaran -->
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

                <!-- Tahun Ajaran -->
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

                <!-- Bank Soal -->
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
                    <p v-if="selectedBank" class="text-sm text-muted-foreground">
                        {{ selectedBank.questions_count ?? 0 }} soal tersedia
                    </p>
                </div>

                <!-- Durasi & Waktu -->
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

                <!-- Opsi -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="pool_count">Jumlah Soal (pool)</Label>
                        <Input id="pool_count" v-model="form.pool_count" type="number" min="1" placeholder="Semua soal" />
                        <p class="text-xs text-muted-foreground">Kosongkan untuk semua soal</p>
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

                <!-- Randomisasi -->
                <div class="flex gap-6">
                    <label class="flex items-center gap-2">
                        <Checkbox
                            :checked="form.is_randomize_questions"
                            @update:checked="form.is_randomize_questions = $event as boolean"
                        />
                        <span class="text-sm">Acak urutan soal</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <Checkbox
                            :checked="form.is_randomize_options"
                            @update:checked="form.is_randomize_options = $event as boolean"
                        />
                        <span class="text-sm">Acak urutan pilihan jawaban</span>
                    </label>
                </div>

                <!-- Kelas Peserta -->
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
                                :checked="form.classroom_ids.includes(c.id)"
                                @update:checked="toggleClassroom(c.id)"
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
                        {{ processing ? 'Menyimpan...' : 'Buat Sesi Ujian' }}
                    </Button>
                    <Button variant="outline" type="button" as-child>
                        <a href="/guru/ujian">Batal</a>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
