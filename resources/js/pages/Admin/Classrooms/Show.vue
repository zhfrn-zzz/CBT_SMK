<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Plus, Trash2, UserPlus } from 'lucide-vue-next';
import type { BreadcrumbItem, Classroom, Subject, User } from '@/types';

const props = defineProps<{
    classroom: Classroom & { students: User[]; academic_year: { name: string; semester: string }; department: { name: string } };
    availableStudents: Pick<User, 'id' | 'name' | 'username'>[];
    teachingAssignments: {
        id: number;
        user_id: number;
        teacher_name: string;
        subject_id: number;
        subject_name: string;
        subject_code: string;
    }[];
    availableTeachers: Pick<User, 'id' | 'name' | 'username'>[];
    availableSubjects: Pick<Subject, 'id' | 'name' | 'code'>[];
    filters: { student_search: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Kelas', href: '/admin/classrooms' },
    { title: props.classroom.name, href: `/admin/classrooms/${props.classroom.id}` },
];

// F2.2: Server-side student search
const studentSearch = ref(props.filters.student_search ?? '');

const searchStudents = useDebounceFn((value: string) => {
    router.get(
        `/admin/classrooms/${props.classroom.id}`,
        { student_search: value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}, 300);

// Student assignment
const selectedStudents = ref<string[]>([]);

function assignStudents() {
    if (selectedStudents.value.length === 0) return;
    router.post(
        `/admin/classrooms/${props.classroom.id}/assign-students`,
        { student_ids: selectedStudents.value.map(Number) },
        { preserveScroll: true },
    );
    selectedStudents.value = [];
}

function removeStudent(userId: number) {
    router.delete(`/admin/classrooms/${props.classroom.id}/remove-student/${userId}`, {
        preserveScroll: true,
    });
}

// Teacher assignment
const teacherForm = useForm({
    user_id: '',
    subject_id: '',
});

function assignTeacher() {
    teacherForm.post(`/admin/classrooms/${props.classroom.id}/assign-teacher`, {
        preserveScroll: true,
        onSuccess: () => {
            teacherForm.reset();
        },
    });
}

function removeTeacher(assignmentId: number) {
    router.delete(`/admin/classrooms/${props.classroom.id}/remove-teacher/${assignmentId}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Kelas ${classroom.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <FlashMessage />

            <!-- Header -->
            <div>
                <h2 class="text-xl font-semibold">{{ classroom.name }}</h2>
                <p class="text-sm text-muted-foreground">
                    {{ classroom.department?.name }} &middot;
                    {{ classroom.academic_year?.name }} ({{ classroom.academic_year?.semester }})
                </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Daftar Siswa -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <UserPlus class="size-5" />
                            Daftar Siswa ({{ classroom.students?.length ?? 0 }})
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- F2.2: Search available students (server-side, limit 50) -->
                        <input
                            v-model="studentSearch"
                            type="text"
                            placeholder="Cari siswa tersedia..."
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            @input="searchStudents(studentSearch)"
                        />
                        <!-- Add students -->
                        <div class="flex gap-2">
                            <Select v-model="selectedStudents[0]">
                                <SelectTrigger class="flex-1">
                                    <SelectValue placeholder="Pilih siswa..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="student in availableStudents"
                                        :key="student.id"
                                        :value="String(student.id)"
                                    >
                                        {{ student.name }} ({{ student.username }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Button size="sm" @click="assignStudents" :disabled="!selectedStudents[0]">
                                <Plus class="size-4" />
                                Tambah
                            </Button>
                        </div>

                        <Separator />

                        <!-- Student list -->
                        <div class="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>NIS</TableHead>
                                        <TableHead>Nama</TableHead>
                                        <TableHead class="w-[60px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="student in classroom.students" :key="student.id">
                                        <TableCell class="font-mono">{{ student.username }}</TableCell>
                                        <TableCell>{{ student.name }}</TableCell>
                                        <TableCell>
                                            <AlertDialog>
                                                <AlertDialogTrigger as-child>
                                                    <Button variant="ghost" size="icon-sm">
                                                        <Trash2 class="size-4 text-destructive" />
                                                    </Button>
                                                </AlertDialogTrigger>
                                                <AlertDialogContent>
                                                    <AlertDialogHeader>
                                                        <AlertDialogTitle>Hapus Siswa dari Kelas</AlertDialogTitle>
                                                        <AlertDialogDescription>
                                                            Hapus <strong>{{ student.name }}</strong> dari kelas {{ classroom.name }}?
                                                        </AlertDialogDescription>
                                                    </AlertDialogHeader>
                                                    <AlertDialogFooter>
                                                        <AlertDialogCancel>Batal</AlertDialogCancel>
                                                        <AlertDialogAction
                                                            class="bg-destructive text-white hover:bg-destructive/90"
                                                            @click="removeStudent(student.id)"
                                                        >
                                                            Hapus
                                                        </AlertDialogAction>
                                                    </AlertDialogFooter>
                                                </AlertDialogContent>
                                            </AlertDialog>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="!classroom.students?.length">
                                        <TableCell :colspan="3" class="text-center text-muted-foreground">
                                            Belum ada siswa di kelas ini.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <!-- Penugasan Guru & Mapel -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            Penugasan Guru & Mata Pelajaran
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Add teacher assignment -->
                        <div class="space-y-3">
                            <Select v-model="teacherForm.user_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih guru..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="teacher in availableTeachers"
                                        :key="teacher.id"
                                        :value="String(teacher.id)"
                                    >
                                        {{ teacher.name }} ({{ teacher.username }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Select v-model="teacherForm.subject_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih mata pelajaran..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="subject in availableSubjects"
                                        :key="subject.id"
                                        :value="String(subject.id)"
                                    >
                                        {{ subject.name }} ({{ subject.code }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Button
                                size="sm"
                                @click="assignTeacher"
                                :disabled="!teacherForm.user_id || !teacherForm.subject_id || teacherForm.processing"
                            >
                                <Plus class="size-4" />
                                Tugaskan
                            </Button>
                        </div>

                        <Separator />

                        <!-- Teaching assignments list -->
                        <div class="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Guru</TableHead>
                                        <TableHead>Mata Pelajaran</TableHead>
                                        <TableHead class="w-[60px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="assignment in teachingAssignments" :key="assignment.id">
                                        <TableCell>{{ assignment.teacher_name }}</TableCell>
                                        <TableCell>
                                            {{ assignment.subject_name }}
                                            <Badge variant="outline" class="ml-1">{{ assignment.subject_code }}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <AlertDialog>
                                                <AlertDialogTrigger as-child>
                                                    <Button variant="ghost" size="icon-sm">
                                                        <Trash2 class="size-4 text-destructive" />
                                                    </Button>
                                                </AlertDialogTrigger>
                                                <AlertDialogContent>
                                                    <AlertDialogHeader>
                                                        <AlertDialogTitle>Hapus Penugasan</AlertDialogTitle>
                                                        <AlertDialogDescription>
                                                            Hapus penugasan <strong>{{ assignment.teacher_name }}</strong> untuk <strong>{{ assignment.subject_name }}</strong>?
                                                        </AlertDialogDescription>
                                                    </AlertDialogHeader>
                                                    <AlertDialogFooter>
                                                        <AlertDialogCancel>Batal</AlertDialogCancel>
                                                        <AlertDialogAction
                                                            class="bg-destructive text-white hover:bg-destructive/90"
                                                            @click="removeTeacher(assignment.id)"
                                                        >
                                                            Hapus
                                                        </AlertDialogAction>
                                                    </AlertDialogFooter>
                                                </AlertDialogContent>
                                            </AlertDialog>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="teachingAssignments.length === 0">
                                        <TableCell :colspan="3" class="text-center text-muted-foreground">
                                            Belum ada penugasan guru.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
