<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { edit } from '@/routes/profile';
import type {
    BreadcrumbItem,
    GuruProfile,
    ProfileUser,
    SiswaProfile,
} from '@/types';
import { getInitials } from '@/composables/useInitials';

type Props = {
    user: ProfileUser;
    siswa?: SiswaProfile;
    guru?: GuruProfile;
};

const props = defineProps<Props>();

const page = usePage();
const currentUser = computed(() => page.props.auth.user);
const isOwnProfile = computed(() => currentUser.value?.id === props.user.id);

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Profil', href: '#' },
];

const roleBadgeVariant = computed(() => {
    switch (props.user.role) {
        case 'admin':
            return 'destructive';
        case 'guru':
            return 'default';
        case 'siswa':
            return 'secondary';
        default:
            return 'outline';
    }
});

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Profil ${user.name}`" />

        <div class="mx-auto max-w-4xl space-y-6 p-4 sm:p-6">
            <!-- Profile Header -->
            <Card>
                <CardContent class="flex flex-col items-center gap-6 p-6 sm:flex-row sm:items-start">
                    <Avatar class="h-24 w-24 shrink-0">
                        <AvatarImage
                            v-if="user.photo_url"
                            :src="user.photo_url"
                            :alt="user.name"
                        />
                        <AvatarFallback class="text-2xl">
                            {{ getInitials(user.name) }}
                        </AvatarFallback>
                    </Avatar>

                    <div class="flex-1 space-y-2 text-center sm:text-left">
                        <div class="flex flex-col items-center gap-2 sm:flex-row">
                            <h1 class="text-2xl font-bold">{{ user.name }}</h1>
                            <Badge :variant="roleBadgeVariant">
                                {{ user.role_label }}
                            </Badge>
                        </div>

                        <div class="space-y-1 text-sm text-muted-foreground">
                            <p>@{{ user.username }}</p>
                            <p v-if="user.email">{{ user.email }}</p>
                            <p v-if="user.phone">{{ user.phone }}</p>
                        </div>

                        <p v-if="user.bio" class="mt-3 text-sm">{{ user.bio }}</p>

                        <p class="text-xs text-muted-foreground">
                            Bergabung sejak {{ formatDate(user.created_at) }}
                        </p>
                    </div>

                    <div v-if="isOwnProfile" class="shrink-0">
                        <Button as-child variant="outline" size="sm">
                            <Link :href="edit()">Edit Profil</Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Siswa Profile -->
            <template v-if="siswa">
                <!-- Classrooms -->
                <Card v-if="siswa.classrooms.length > 0">
                    <CardHeader>
                        <CardTitle>Kelas</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-wrap gap-2">
                            <Badge
                                v-for="classroom in siswa.classrooms"
                                :key="classroom.id"
                                variant="outline"
                            >
                                {{ classroom.name }}
                                <span
                                    v-if="classroom.department"
                                    class="text-muted-foreground"
                                >
                                    &mdash; {{ classroom.department }}
                                </span>
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <!-- Attendance Summary -->
                <Card>
                    <CardHeader>
                        <CardTitle>Rekap Kehadiran</CardTitle>
                        <CardDescription>
                            Total {{ siswa.attendance.total }} pertemuan
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
                            <div class="text-center">
                                <p class="text-2xl font-bold text-green-600">
                                    {{ siswa.attendance.hadir }}
                                </p>
                                <p class="text-xs text-muted-foreground">Hadir</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-blue-600">
                                    {{ siswa.attendance.izin }}
                                </p>
                                <p class="text-xs text-muted-foreground">Izin</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-yellow-600">
                                    {{ siswa.attendance.sakit }}
                                </p>
                                <p class="text-xs text-muted-foreground">Sakit</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-red-600">
                                    {{ siswa.attendance.alfa }}
                                </p>
                                <p class="text-xs text-muted-foreground">Alfa</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold">
                                    {{ siswa.attendance.percentage }}%
                                </p>
                                <p class="text-xs text-muted-foreground">Persentase</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Exam Results -->
                <Card v-if="siswa.exam_results.length > 0">
                    <CardHeader>
                        <CardTitle>Hasil Ujian</CardTitle>
                        <CardDescription>
                            {{ siswa.exam_results.length }} ujian terakhir
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Ujian</TableHead>
                                    <TableHead>Mata Pelajaran</TableHead>
                                    <TableHead class="text-center">Nilai</TableHead>
                                    <TableHead class="text-center">KKM</TableHead>
                                    <TableHead class="text-center">Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="(result, index) in siswa.exam_results"
                                    :key="index"
                                >
                                    <TableCell class="font-medium">
                                        {{ result.exam_name }}
                                    </TableCell>
                                    <TableCell>{{ result.subject }}</TableCell>
                                    <TableCell class="text-center">
                                        {{ result.score !== null ? result.score : '-' }}
                                    </TableCell>
                                    <TableCell class="text-center">
                                        {{ result.kkm }}
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <Badge
                                            v-if="result.pass_status"
                                            :variant="
                                                result.pass_status === 'lulus'
                                                    ? 'default'
                                                    : 'destructive'
                                            "
                                        >
                                            {{
                                                result.pass_status === 'lulus'
                                                    ? 'Lulus'
                                                    : 'Remedial'
                                            }}
                                        </Badge>
                                        <span v-else class="text-muted-foreground"
                                            >-</span
                                        >
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </template>

            <!-- Guru Profile -->
            <template v-if="guru">
                <!-- Subjects -->
                <Card v-if="guru.subjects.length > 0">
                    <CardHeader>
                        <CardTitle>Mata Pelajaran yang Diampu</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-wrap gap-2">
                            <Badge
                                v-for="subject in guru.subjects"
                                :key="subject.id"
                                variant="outline"
                            >
                                {{ subject.name }}
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <!-- Classrooms -->
                <Card v-if="guru.classrooms.length > 0">
                    <CardHeader>
                        <CardTitle>Kelas yang Diajar</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-wrap gap-2">
                            <Badge
                                v-for="classroom in guru.classrooms"
                                :key="classroom.id"
                                variant="outline"
                            >
                                {{ classroom.name }}
                                <span
                                    v-if="classroom.department"
                                    class="text-muted-foreground"
                                >
                                    &mdash; {{ classroom.department }}
                                </span>
                            </Badge>
                        </div>
                    </CardContent>
                </Card>
            </template>
        </div>
    </AppLayout>
</template>
