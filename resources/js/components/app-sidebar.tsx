import { Link, usePage } from '@inertiajs/react';
import { BookOpen, LayoutGrid, ShieldCheck, Users } from 'lucide-react';
import type { SharedData } from '@/types';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { useCurrentUrl } from '@/hooks/use-current-url';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
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

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const { isCurrentUrl } = useCurrentUrl();
    const isAdmin = auth.user.roles?.includes('admin') ?? false;

    const navItems = [...mainNavItems];
    if (isAdmin) {
        navItems.push({
            title: 'Manajemen Pelatihan',
            href: '/admin/trainings',
            icon: BookOpen,
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

    const adminBottomNavItems: NavItem[] = [
        {
            title: 'Manajemen Pengguna',
            href: '/admin/users',
            icon: Users,
        },
        {
            title: 'Pengelolaan Permissions',
            href: '/admin/roles-permissions',
            icon: ShieldCheck,
        },
    ];

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
                {isAdmin && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>Sistem & Akses</SidebarGroupLabel>
                        <SidebarMenu>
                            {adminBottomNavItems.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isCurrentUrl(item.href)}
                                        tooltip={{ children: item.title }}
                                    >
                                        <Link href={item.href} prefetch>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
