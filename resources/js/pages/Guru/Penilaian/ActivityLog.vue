<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
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
import { ArrowLeft, AlertTriangle, Monitor, Copy, MousePointer, Maximize } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    examSession: {
        id: number;
        name: string;
        subject: string;
        max_tab_switches: number | null;
    };
    attempt: {
        id: number;
        user: { id: number; name: string; username: string };
        started_at: string;
        submitted_at: string | null;
        is_force_submitted: boolean;
        ip_address: string | null;
        user_agent: string | null;
    };
    logs: Array<{
        id: number;
        event_type: string;
        event_label: string;
        description: string | null;
        created_at: string;
    }>;
    summary: {
        total: number;
        tab_switches: number;
        fullscreen_exits: number;
        copy_attempts: number;
        right_clicks: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Penilaian', href: '/guru/grading' },
    { title: props.examSession.name, href: `/guru/grading/${props.examSession.id}` },
    { title: `Log Aktivitas - ${props.attempt.user.name}`, href: '#' },
];

function formatDateTime(date: string) {
    return new Date(date).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

function getEventIcon(type: string) {
    switch (type) {
        case 'tab_switch': return AlertTriangle;
        case 'fullscreen_exit': return Maximize;
        case 'copy_attempt': return Copy;
        case 'right_click': return MousePointer;
        default: return Monitor;
    }
}

function getEventBadgeVariant(type: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (type) {
        case 'tab_switch': return 'destructive';
        case 'fullscreen_exit': return 'default';
        case 'copy_attempt': return 'destructive';
        case 'right_click': return 'secondary';
        default: return 'outline';
    }
}
</script>

<template>
    <Head :title="`Log Aktivitas - ${attempt.user.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Back Button -->
            <div>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/guru/grading/${examSession.id}`">
                        <ArrowLeft class="mr-1 size-4" />
                        Kembali ke Penilaian
                    </Link>
                </Button>
            </div>

            <!-- Student Info -->
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-lg">
                        {{ attempt.user.name }}
                        <span class="text-sm font-normal text-muted-foreground">({{ attempt.user.username }})</span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                        <div>
                            <span class="text-muted-foreground">Ujian:</span>
                            <p class="font-medium">{{ examSession.name }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Mulai:</span>
                            <p class="font-medium">{{ formatDateTime(attempt.started_at) }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Selesai:</span>
                            <p class="font-medium">
                                {{ attempt.submitted_at ? formatDateTime(attempt.submitted_at) : '-' }}
                                <Badge v-if="attempt.is_force_submitted" variant="destructive" class="ml-1">
                                    Auto-submit
                                </Badge>
                            </p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">IP Address:</span>
                            <p class="font-medium font-mono">{{ attempt.ip_address ?? '-' }}</p>
                        </div>
                    </div>
                    <div v-if="attempt.user_agent" class="mt-2 text-xs text-muted-foreground">
                        <span class="font-medium">User Agent:</span> {{ attempt.user_agent }}
                    </div>
                </CardContent>
            </Card>

            <!-- Summary Cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <Card>
                    <CardContent class="pt-4">
                        <p class="text-2xl font-bold">{{ summary.total }}</p>
                        <p class="text-sm text-muted-foreground">Total Pelanggaran</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-4">
                        <p class="text-2xl font-bold text-red-600">
                            {{ summary.tab_switches }}
                            <span v-if="examSession.max_tab_switches !== null" class="text-sm font-normal text-muted-foreground">
                                / {{ examSession.max_tab_switches }}
                            </span>
                        </p>
                        <p class="text-sm text-muted-foreground">Pindah Tab</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-4">
                        <p class="text-2xl font-bold">{{ summary.fullscreen_exits }}</p>
                        <p class="text-sm text-muted-foreground">Keluar Fullscreen</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-4">
                        <p class="text-2xl font-bold">{{ summary.copy_attempts }}</p>
                        <p class="text-sm text-muted-foreground">Copy Attempt</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-4">
                        <p class="text-2xl font-bold">{{ summary.right_clicks }}</p>
                        <p class="text-sm text-muted-foreground">Klik Kanan</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Activity Log Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Log Aktivitas</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="logs.length === 0" class="py-8 text-center text-muted-foreground">
                        Tidak ada log aktivitas untuk siswa ini.
                    </div>
                    <Table v-else>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-12">#</TableHead>
                                <TableHead>Jenis Pelanggaran</TableHead>
                                <TableHead>Deskripsi</TableHead>
                                <TableHead>Waktu</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(log, index) in logs" :key="log.id">
                                <TableCell class="font-mono text-muted-foreground">
                                    {{ logs.length - index }}
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="getEventBadgeVariant(log.event_type)" class="gap-1">
                                        <component :is="getEventIcon(log.event_type)" class="size-3" />
                                        {{ log.event_label }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-sm text-muted-foreground">
                                    {{ log.description ?? '-' }}
                                </TableCell>
                                <TableCell class="whitespace-nowrap text-sm font-mono">
                                    {{ formatDateTime(log.created_at) }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
