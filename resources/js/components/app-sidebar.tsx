import { Link, usePage } from '@inertiajs/react';
import { BookOpen, FolderGit2, LayoutGrid, Users } from 'lucide-react';
import type { SharedData } from '@/types';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const isAdmin = auth.user.roles?.includes('admin') ?? false;

    const navItems = [...mainNavItems];
    if (isAdmin) {
        navItems.push({
            title: 'Manajemen Pelatihan',
            href: '/admin/trainings',
            icon: BookOpen,
        });
        navItems.push({
            title: 'Manajemen Pengguna',
            href: '/admin/users',
            icon: Users,
        });
        navItems.push({
            title: 'Ekspor Laporan (CSV)',
            href: '/admin/reports/comments',
            icon: BookOpen,
        });
    }
    
    if (auth.user.roles?.includes('reviewer')) {
        navItems.push({
            title: 'Tugas Reviu',
            href: '/reviewer/reviews',
            icon: BookOpen,
        });
    }

    if (auth.user.roles?.includes('pengembang') || auth.user.roles?.includes('developer')) {
        navItems.push({
            title: 'Mata Pelatihan Saya',
            href: '/developer/subjects',
            icon: BookOpen,
        });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
