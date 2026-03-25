<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    Award,
    BarChart3,
    BookOpen,
    Calendar,
    CheckCircle,
    ClipboardList,
    FileText,
    FolderOpen,
    GraduationCap,
    HardDrive,
    LayoutDashboard,
    Library,
    Megaphone,
    MessageCircle,
    MessageSquare,
    Monitor,
    School,
    UserCheck,
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
import type { NavGroup, UserRole } from '@/types';

const page = usePage();
const userRole = computed(() => (page.props.auth as { user: { role: UserRole } }).user.role);

const adminNavGroups: NavGroup[] = [
    {
        label: 'Menu Utama',
        items: [
            { title: 'Dashboard', href: '/admin/dashboard', icon: LayoutDashboard },
        ],
    },
    {
        label: 'Manajemen',
        items: [
            { title: 'Pengguna', href: '/admin/users', icon: Users },
            { title: 'Jurusan', href: '/admin/departments', icon: GraduationCap },
            { title: 'Kelas', href: '/admin/classrooms', icon: School },
            { title: 'Mata Pelajaran', href: '/admin/subjects', icon: BookOpen },
            { title: 'Tahun Akademik', href: '/admin/academic-years', icon: Calendar },
        ],
    },
    {
        label: 'Sistem',
        items: [
            { title: 'Kategori Forum', href: '/admin/forum-categories', icon: MessageSquare },
            { title: 'Penyimpanan', href: '/admin/storage', icon: HardDrive },
            { title: 'Pertukaran Data', href: '/admin/data-exchange/export-students', icon: ArrowLeftRight },
            { title: 'Audit Log', href: '/admin/audit-log', icon: FileText },
            { title: 'Analitik', href: '/admin/analytics', icon: BarChart3 },
        ],
    },
];

const guruNavGroups: NavGroup[] = [
    {
        label: 'Menu Utama',
        items: [
            { title: 'Dashboard', href: '/guru/dashboard', icon: LayoutDashboard },
            { title: 'Kalender', href: '/guru/kalender', icon: Calendar },
        ],
    },
    {
        label: 'Pembelajaran',
        items: [
            { title: 'Materi', href: '/guru/materi', icon: FileText },
            { title: 'Tugas', href: '/guru/tugas', icon: ClipboardList },
            { title: 'Pengumuman', href: '/guru/pengumuman', icon: Megaphone },
        ],
    },
    {
        label: 'Ujian',
        items: [
            { title: 'Bank Soal', href: '/guru/bank-soal', icon: Library },
            { title: 'Ujian', href: '/guru/ujian', icon: Monitor },
            { title: 'Penilaian', href: '/guru/grading', icon: CheckCircle },
        ],
    },
    {
        label: 'Kelas',
        items: [
            { title: 'Presensi', href: '/guru/presensi', icon: UserCheck },
            { title: 'Forum', href: '/forum', icon: MessageCircle },
            { title: 'File Manager', href: '/guru/file-manager', icon: FolderOpen },
        ],
    },
];

const siswaNavGroups: NavGroup[] = [
    {
        label: 'Menu Utama',
        items: [
            { title: 'Dashboard', href: '/siswa/dashboard', icon: LayoutDashboard },
            { title: 'Kalender', href: '/siswa/kalender', icon: Calendar },
        ],
    },
    {
        label: 'Pembelajaran',
        items: [
            { title: 'Materi', href: '/siswa/materi', icon: FileText },
            { title: 'Tugas', href: '/siswa/tugas', icon: ClipboardList },
            { title: 'Pengumuman', href: '/siswa/pengumuman', icon: Megaphone },
        ],
    },
    {
        label: 'Ujian & Nilai',
        items: [
            { title: 'Ujian', href: '/siswa/ujian', icon: Monitor },
            { title: 'Nilai', href: '/siswa/nilai', icon: Award },
        ],
    },
    {
        label: 'Lainnya',
        items: [
            { title: 'Presensi', href: '/siswa/presensi', icon: UserCheck },
            { title: 'Forum', href: '/forum', icon: MessageCircle },
        ],
    },
];

const navGroupsByRole: Record<UserRole, NavGroup[]> = {
    admin: adminNavGroups,
    guru: guruNavGroups,
    siswa: siswaNavGroups,
};

const navGroups = computed(() => navGroupsByRole[userRole.value] ?? []);
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
            <NavMain :groups="navGroups" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
