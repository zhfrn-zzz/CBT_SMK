<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login } from '@/routes';
import {
    Monitor, Library, Users, BarChart3,
    CalendarDays, LogIn, Megaphone, Clock,
    BookOpen, LayoutDashboard,
} from 'lucide-vue-next';

interface SchoolInfo {
    name: string;
    address: string;
    logo_path: string;
    tagline: string;
}

interface PublicAnnouncement {
    id: number;
    title: string;
    content: string;
    published_at: string;
    is_pinned: boolean;
}

interface ExamSchedule {
    id: number;
    name: string;
    subject: { id: number; name: string } | null;
    starts_at: string;
    ends_at: string;
    duration_minutes: number;
}

const props = defineProps<{
    school: SchoolInfo;
    announcements: PublicAnnouncement[];
    examSchedules: ExamSchedule[];
}>();

const features = [
    { icon: Monitor, title: 'CBT Online', description: 'Ujian berbasis komputer dengan pengawasan real-time dan anti-kecurangan.' },
    { icon: Library, title: 'Bank Soal', description: 'Kelola ribuan soal terstruktur berdasarkan mata pelajaran dan kompetensi.' },
    { icon: Users, title: 'Manajemen Kelas', description: 'Atur kelas, siswa, dan guru dalam satu platform terintegrasi.' },
    { icon: BarChart3, title: 'Analisis Nilai', description: 'Laporan dan analisis hasil ujian secara otomatis dan komprehensif.' },
];

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function formatDateTime(dateString: string): string {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function stripHtml(html: string): string {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
}
</script>

<template>
    <Head :title="school.name + ' - Beranda'">
        <meta name="description" :content="school.tagline || 'Sistem Pembelajaran dan Ujian Online'" />
    </Head>

    <div class="min-h-screen bg-background">
        <!-- Navbar -->
        <header class="sticky top-0 z-50 border-b border-border bg-background">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <img
                        :src="'/' + school.logo_path"
                        :alt="school.name"
                        class="h-8 w-8 object-contain"
                        @error="($event.target as HTMLImageElement).style.display = 'none'"
                    />
                    <span class="text-base font-semibold text-foreground">{{ school.name }}</span>
                </div>
                <nav>
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
                    >
                        <LayoutDashboard class="h-4 w-4" />
                        Dashboard
                    </Link>
                    <Link
                        v-else
                        :href="login()"
                        class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
                    >
                        <LogIn class="h-4 w-4" />
                        Masuk
                    </Link>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="bg-background">
            <div class="mx-auto flex max-w-7xl flex-col items-center px-4 py-20 text-center sm:px-6 lg:px-8">
                <img
                    :src="'/' + school.logo_path"
                    :alt="school.name"
                    class="mb-6 h-20 w-20 object-contain"
                    @error="($event.target as HTMLImageElement).style.display = 'none'"
                />
                <h1 class="text-2xl font-bold text-foreground">{{ school.name }}</h1>
                <p v-if="school.tagline" class="mt-2 text-muted-foreground">{{ school.tagline }}</p>
                <p v-if="school.address" class="mt-1 text-sm text-muted-foreground">{{ school.address }}</p>
                <div class="mt-8">
                    <Link
                        v-if="!$page.props.auth.user"
                        :href="login()"
                        class="inline-flex items-center gap-2 rounded-md bg-primary px-6 py-2.5 text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
                    >
                        <LogIn class="h-4 w-4" />
                        Masuk ke Sistem
                    </Link>
                    <Link
                        v-else
                        :href="dashboard()"
                        class="inline-flex items-center gap-2 rounded-md bg-primary px-6 py-2.5 text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
                    >
                        <LayoutDashboard class="h-4 w-4" />
                        Buka Dashboard
                    </Link>
                </div>
            </div>
        </section>

        <!-- Feature Highlights -->
        <section class="border-t border-border bg-background">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="feature in features"
                        :key="feature.title"
                        class="rounded-lg border border-border bg-card p-5"
                    >
                        <component :is="feature.icon" class="h-5 w-5 text-muted-foreground" />
                        <h3 class="mt-3 font-semibold text-card-foreground">{{ feature.title }}</h3>
                        <p class="mt-1 text-sm text-muted-foreground">{{ feature.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Content Sections -->
        <main class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
            <div class="grid gap-10 lg:grid-cols-2">
                <!-- Pengumuman Publik -->
                <section>
                    <div class="mb-5 flex items-center gap-2">
                        <Megaphone class="h-4 w-4 text-muted-foreground" />
                        <h2 class="text-lg font-semibold text-foreground">Pengumuman Publik</h2>
                    </div>

                    <div v-if="announcements.length > 0" class="space-y-3">
                        <article
                            v-for="announcement in announcements"
                            :key="announcement.id"
                            class="rounded-lg border border-border bg-card p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="font-semibold text-card-foreground">{{ announcement.title }}</h3>
                                <span
                                    v-if="announcement.is_pinned"
                                    class="shrink-0 rounded-md bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/30 dark:text-amber-400"
                                >
                                    Disematkan
                                </span>
                            </div>
                            <p class="mt-2 line-clamp-2 text-sm text-muted-foreground">
                                {{ stripHtml(announcement.content) }}
                            </p>
                            <time class="mt-3 block text-xs text-muted-foreground/60">
                                {{ formatDate(announcement.published_at) }}
                            </time>
                        </article>
                    </div>

                    <div v-else class="rounded-lg border border-dashed border-border bg-card p-10 text-center">
                        <Megaphone class="mx-auto h-10 w-10 text-muted-foreground/40" />
                        <p class="mt-3 text-sm text-muted-foreground">Belum ada pengumuman</p>
                    </div>
                </section>

                <!-- Jadwal Ujian -->
                <section>
                    <div class="mb-5 flex items-center gap-2">
                        <CalendarDays class="h-4 w-4 text-muted-foreground" />
                        <h2 class="text-lg font-semibold text-foreground">Jadwal Ujian Mendatang</h2>
                    </div>

                    <div v-if="examSchedules.length > 0" class="space-y-3">
                        <div
                            v-for="exam in examSchedules"
                            :key="exam.id"
                            class="rounded-lg border border-border bg-card p-4"
                        >
                            <h3 class="font-semibold text-card-foreground">{{ exam.name }}</h3>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1.5 text-sm text-muted-foreground">
                                <span v-if="exam.subject" class="inline-flex items-center gap-1.5">
                                    <BookOpen class="h-3.5 w-3.5" />
                                    {{ exam.subject.name }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <CalendarDays class="h-3.5 w-3.5" />
                                    {{ formatDateTime(exam.starts_at) }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <Clock class="h-3.5 w-3.5" />
                                    {{ exam.duration_minutes }} menit
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-else class="rounded-lg border border-dashed border-border bg-card p-10 text-center">
                        <CalendarDays class="mx-auto h-10 w-10 text-muted-foreground/40" />
                        <p class="mt-3 text-sm text-muted-foreground">Belum ada jadwal ujian</p>
                    </div>
                </section>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-border bg-background py-8">
            <div class="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                <p class="text-sm text-muted-foreground">&copy; {{ new Date().getFullYear() }} {{ school.name }}. Sistem CBT &amp; LMS.</p>
            </div>
        </footer>
    </div>
</template>
