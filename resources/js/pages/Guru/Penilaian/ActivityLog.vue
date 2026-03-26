<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatsCard from '@/Components/StatsCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
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
import { Activity, AlertTriangle, ArrowLeft, Copy, Maximize, Monitor, MousePointer } from 'lucide-vue-next';
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
        <div class="flex h-full flex-1 flex-col gap-4 rounded-lg p-4">
            <!-- Back Button -->
            <div>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/guru/grading/${examSession.id}`">
                        <ArrowLeft class="mr-1 size-4" />
                        Kembali ke Penilaian
                    </Link>
                </Button>
            </div>

            <PageHeader title="Log Aktivitas" :description="attempt.user.name" :icon="Activity" />

            <!-- Student Info -->
            <Card>
                <CardContent class="p-6">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                        <div>
                            <span class="text-sm font-semibold text-muted-foreground">Ujian</span>
                            <p class="text-base text-foreground">{{ examSession.name }}</p>
                        </div>
                        <div>
                            <span class="text-sm font-semibold text-muted-foreground">Mulai</span>
                            <p class="text-base text-foreground">{{ formatDateTime(attempt.started_at) }}</p>
                        </div>
                        <div>
                            <span class="text-sm font-semibold text-muted-foreground">Selesai</span>
                            <p class="text-base text-foreground">
                                {{ attempt.submitted_at ? formatDateTime(attempt.submitted_at) : '-' }}
                                <Badge v-if="attempt.is_force_submitted" variant="destructive" class="ml-1">
                                    Auto-submit
                                </Badge>
                            </p>
                        </div>
                        <div>
                            <span class="text-sm font-semibold text-muted-foreground">IP Address</span>
                            <p class="text-base text-foreground font-mono">{{ attempt.ip_address ?? '-' }}</p>
                        </div>
                    </div>
                    <div v-if="attempt.user_agent" class="mt-2 text-xs text-muted-foreground">
                        <span class="font-medium">User Agent:</span> {{ attempt.user_agent }}
                    </div>
                </CardContent>
            </Card>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                <StatsCard title="Total Pelanggaran" :value="summary.total" :icon="AlertTriangle" icon-color="bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400" />
                <StatsCard title="Pindah Tab" :value="summary.tab_switches" :icon="AlertTriangle" icon-color="bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400" :trend="examSession.max_tab_switches !== null ? `/ ${examSession.max_tab_switches}` : undefined" />
                <StatsCard title="Keluar Fullscreen" :value="summary.fullscreen_exits" :icon="Maximize" />
                <StatsCard title="Copy Attempt" :value="summary.copy_attempts" :icon="Copy" />
                <StatsCard title="Klik Kanan" :value="summary.right_clicks" :icon="MousePointer" />
            </div>

            <!-- Activity Log Table -->
            <div class="overflow-x-auto rounded-lg border bg-card">
                <Table class="min-w-[500px]">
                    <TableHeader>
                        <TableRow class="bg-slate-50">
                            <TableHead class="w-12 text-xs font-semibold uppercase tracking-wider">#</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Jenis Pelanggaran</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Deskripsi</TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider">Waktu</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(log, index) in logs" :key="log.id" class="hover:bg-slate-50/50 even:bg-slate-50/30 transition-colors">
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
            </div>

            <EmptyState
                v-if="logs.length === 0"
                :icon="Activity"
                title="Tidak ada log aktivitas"
                description="Tidak ada log aktivitas untuk siswa ini."
            />
        </div>
    </AppLayout>
</template>
