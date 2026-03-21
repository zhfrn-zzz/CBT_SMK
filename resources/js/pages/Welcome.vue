<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login } from '@/routes';
import { CalendarDays, LogIn, Megaphone, Clock, BookOpen, LayoutDashboard } from 'lucide-vue-next';

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

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950">
        <!-- Header / Navbar -->
        <header class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <img
                        :src="'/' + school.logo_path"
                        :alt="school.name"
                        class="h-10 w-10 rounded-full object-contain"
                        @error="($event.target as HTMLImageElement).style.display = 'none'"
                    />
                    <div>
                        <h1 class="text-lg font-bold text-gray-900 dark:text-white">{{ school.name }}</h1>
                        <p v-if="school.tagline" class="text-xs text-gray-500 dark:text-gray-400">{{ school.tagline }}</p>
                    </div>
                </div>
                <nav>
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                    >
                        <LayoutDashboard class="h-4 w-4" />
                        Dashboard
                    </Link>
                    <Link
                        v-else
                        :href="login()"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                    >
                        <LogIn class="h-4 w-4" />
                        Masuk
                    </Link>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="bg-gradient-to-br from-blue-600 to-blue-800 py-16 text-white dark:from-blue-800 dark:to-blue-950">
            <div class="mx-auto max-w-6xl px-4 text-center sm:px-6 lg:px-8">
                <img
                    :src="'/' + school.logo_path"
                    :alt="school.name"
                    class="mx-auto mb-6 h-24 w-24 rounded-full bg-white/10 object-contain p-2"
                    @error="($event.target as HTMLImageElement).style.display = 'none'"
                />
                <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ school.name }}</h2>
                <p v-if="school.tagline" class="mt-3 text-lg text-blue-100">{{ school.tagline }}</p>
                <p v-if="school.address" class="mt-2 text-sm text-blue-200">{{ school.address }}</p>
                <div class="mt-8">
                    <Link
                        v-if="!$page.props.auth.user"
                        :href="login()"
                        class="inline-flex items-center gap-2 rounded-lg bg-white px-6 py-3 text-sm font-semibold text-blue-700 shadow-lg hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-600"
                    >
                        <LogIn class="h-5 w-5" />
                        Masuk ke Sistem
                    </Link>
                    <Link
                        v-else
                        :href="dashboard()"
                        class="inline-flex items-center gap-2 rounded-lg bg-white px-6 py-3 text-sm font-semibold text-blue-700 shadow-lg hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-600"
                    >
                        <LayoutDashboard class="h-5 w-5" />
                        Buka Dashboard
                    </Link>
                </div>
            </div>
        </section>

        <!-- Content Sections -->
        <main class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-2">
                <!-- Pengumuman Section -->
                <section v-if="announcements.length > 0">
                    <div class="mb-4 flex items-center gap-2">
                        <Megaphone class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Pengumuman</h3>
                    </div>
                    <div class="space-y-3">
                        <article
                            v-for="announcement in announcements"
                            :key="announcement.id"
                            class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <h4 class="font-medium text-gray-900 dark:text-white">{{ announcement.title }}</h4>
                                <span
                                    v-if="announcement.is_pinned"
                                    class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-400"
                                >
                                    Disematkan
                                </span>
                            </div>
                            <p class="mt-1 line-clamp-2 text-sm text-gray-600 dark:text-gray-400">
                                {{ stripHtml(announcement.content) }}
                            </p>
                            <time class="mt-2 block text-xs text-gray-400 dark:text-gray-500">
                                {{ formatDate(announcement.published_at) }}
                            </time>
                        </article>
                    </div>
                </section>

                <!-- Empty Pengumuman -->
                <section v-else>
                    <div class="mb-4 flex items-center gap-2">
                        <Megaphone class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Pengumuman</h3>
                    </div>
                    <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center dark:border-gray-700 dark:bg-gray-900">
                        <Megaphone class="mx-auto h-8 w-8 text-gray-400" />
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum ada pengumuman</p>
                    </div>
                </section>

                <!-- Jadwal Ujian Section -->
                <section v-if="examSchedules.length > 0">
                    <div class="mb-4 flex items-center gap-2">
                        <CalendarDays class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Jadwal Ujian Mendatang</h3>
                    </div>
                    <div class="space-y-3">
                        <div
                            v-for="exam in examSchedules"
                            :key="exam.id"
                            class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                        >
                            <h4 class="font-medium text-gray-900 dark:text-white">{{ exam.name }}</h4>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-600 dark:text-gray-400">
                                <span v-if="exam.subject" class="inline-flex items-center gap-1">
                                    <BookOpen class="h-3.5 w-3.5" />
                                    {{ exam.subject.name }}
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <CalendarDays class="h-3.5 w-3.5" />
                                    {{ formatDateTime(exam.starts_at) }}
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <Clock class="h-3.5 w-3.5" />
                                    {{ exam.duration_minutes }} menit
                                </span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-gray-200 bg-white py-6 dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto max-w-6xl px-4 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6 lg:px-8">
                <p>&copy; {{ new Date().getFullYear() }} {{ school.name }}. Sistem CBT &amp; LMS.</p>
            </div>
        </footer>
    </div>
</template>
