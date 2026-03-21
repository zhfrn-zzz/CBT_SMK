<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    BookOpen,
    CalendarDays,
    ClipboardList,
    Database,
    FileText,
    FolderOpen,
    GraduationCap,
    HardDrive,
    LayoutGrid,
    Library,
    Megaphone,
    MessageCircle,
    MessageSquare,
    NotebookPen,
    School,
    ShieldCheck,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem, UserRole } from '@/types';

const page = usePage();
const userRole = computed(() => (page.props.auth as { user: { role: UserRole } }).user.role);

const adminNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Manajemen Pengguna',
        href: '/admin/users',
        icon: Users,
    },
    {
        title: 'Tahun Ajaran',
        href: '/admin/academic-years',
        icon: School,
    },
    {
        title: 'Jurusan',
        href: '/admin/departments',
        icon: Library,
    },
    {
        title: 'Kelas',
        href: '/admin/classrooms',
        icon: GraduationCap,
    },
    {
        title: 'Mata Pelajaran',
        href: '/admin/subjects',
        icon: BookOpen,
    },
    {
        title: 'Log Audit',
        href: '/admin/audit-log',
        icon: ShieldCheck,
    },
    {
        title: 'Analitik',
        href: '/admin/analytics',
        icon: BarChart3,
    },
    {
        title: 'Data Exchange',
        href: '/admin/data-exchange/export-students',
        icon: Database,
    },
    {
        title: 'Forum',
        href: '/forum',
        icon: MessageSquare,
    },
    {
        title: 'Kategori Forum',
        href: '/admin/forum-categories',
        icon: FolderOpen,
    },
    {
        title: 'Manajemen Storage',
        href: '/admin/storage',
        icon: HardDrive,
    },
];

const guruNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/guru/dashboard',
        icon: LayoutGrid,
    },
    // Ujian group
    {
        title: 'Bank Soal',
        href: '/guru/bank-soal',
        icon: Library,
    },
    {
        title: 'Ujian',
        href: '/guru/ujian',
        icon: ClipboardList,
    },
    {
        title: 'Penilaian',
        href: '/guru/grading',
        icon: FileText,
    },
    // Pembelajaran group
    {
        title: 'Materi',
        href: '/guru/materi',
        icon: BookOpen,
    },
    {
        title: 'Tugas',
        href: '/guru/tugas',
        icon: NotebookPen,
    },
    {
        title: 'Forum Diskusi',
        href: '/guru/forum',
        icon: MessageCircle,
    },
    {
        title: 'Forum Sekolah',
        href: '/forum',
        icon: MessageSquare,
    },
    {
        title: 'Pengumuman',
        href: '/guru/pengumuman',
        icon: Megaphone,
    },
    {
        title: 'Presensi',
        href: '/guru/presensi',
        icon: ClipboardList,
    },
    {
        title: 'Kalender',
        href: '/guru/kalender',
        icon: CalendarDays,
    },
    {
        title: 'File Manager',
        href: '/guru/file-manager',
        icon: FolderOpen,
    },
];

const siswaNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/siswa/dashboard',
        icon: LayoutGrid,
    },
    // Ujian group
    {
        title: 'Ujian',
        href: '/siswa/ujian',
        icon: ClipboardList,
    },
    {
        title: 'Nilai',
        href: '/siswa/nilai',
        icon: FileText,
    },
    // Pembelajaran group
    {
        title: 'Materi',
        href: '/siswa/materi',
        icon: BookOpen,
    },
    {
        title: 'Tugas',
        href: '/siswa/tugas',
        icon: NotebookPen,
    },
    {
        title: 'Forum Diskusi',
        href: '/siswa/forum',
        icon: MessageCircle,
    },
    {
        title: 'Forum Sekolah',
        href: '/forum',
        icon: MessageSquare,
    },
    {
        title: 'Pengumuman',
        href: '/siswa/pengumuman',
        icon: Megaphone,
    },
    {
        title: 'Presensi',
        href: '/siswa/presensi',
        icon: ClipboardList,
    },
    {
        title: 'Kalender',
        href: '/siswa/kalender',
        icon: CalendarDays,
    },
];

const navItemsByRole: Record<UserRole, NavItem[]> = {
    admin: adminNavItems,
    guru: guruNavItems,
    siswa: siswaNavItems,
};

const navItems = computed(() => navItemsByRole[userRole.value] ?? []);
const dashboardHref = computed(() => {
    const roleRoutes: Record<UserRole, string> = {
        admin: '/admin/dashboard',
        guru: '/guru/dashboard',
        siswa: '/siswa/dashboard',
    };
    return roleRoutes[userRole.value] ?? '/dashboard';
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardHref">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="navItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
