import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { BookOpen, Files, Users, CheckCircle, Clock } from 'lucide-react';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

type Stats = {
    admin?: {
        total_trainings: number;
        total_materials: number;
        total_reviewers: number;
        reviews_submitted: number;
        reviews_draft: number;
    };
    developer?: {
        total_subjects: number;
        total_materials: number;
    };
    reviewer?: {
        pending_tasks: number;
        completed_tasks: number;
    };
};

export default function Dashboard({ stats }: { stats: Stats }) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    {stats.admin && (
                        <>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Total Pelatihan</CardTitle>
                                    <BookOpen className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{stats.admin.total_trainings}</div>
                                    <p className="text-xs text-muted-foreground">Pelatihan terdaftar</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Total Materi</CardTitle>
                                    <Files className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{stats.admin.total_materials}</div>
                                    <p className="text-xs text-muted-foreground">Materi pelatihan</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Status Reviu</CardTitle>
                                    <CheckCircle className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{stats.admin.reviews_submitted} <span className="text-sm font-normal text-muted-foreground">/ {stats.admin.reviews_draft + stats.admin.reviews_submitted} Selesai</span></div>
                                    <p className="text-xs text-muted-foreground">{stats.admin.reviews_draft} sedang dikerjakan</p>
                                </CardContent>
                            </Card>
                        </>
                    )}

                    {stats.developer && (
                        <>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Mata Pelatihan Saya</CardTitle>
                                    <BookOpen className="h-4 w-4 text-blue-500" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{stats.developer.total_subjects}</div>
                                    <p className="text-xs text-muted-foreground">Mata pelatihan diampu</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Total Materi Diampu</CardTitle>
                                    <Files className="h-4 w-4 text-purple-500" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{stats.developer.total_materials}</div>
                                    <p className="text-xs text-muted-foreground">Materi pelatihan</p>
                                </CardContent>
                            </Card>
                        </>
                    )}

                    {stats.reviewer && (
                        <>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Tugas Menunggu</CardTitle>
                                    <Clock className="h-4 w-4 text-orange-500" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{stats.reviewer.pending_tasks}</div>
                                    <p className="text-xs text-muted-foreground">Materi perlu diulas</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Tugas Selesai</CardTitle>
                                    <CheckCircle className="h-4 w-4 text-green-500" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{stats.reviewer.completed_tasks}</div>
                                    <p className="text-xs text-muted-foreground">Materi telah diulas</p>
                                </CardContent>
                            </Card>
                        </>
                    )}
                </div>
                
                <div className="min-h-[100vh] flex-1 rounded-xl bg-muted/50 md:min-h-min flex items-center justify-center p-8">
                    <div className="text-center space-y-2">
                        <h2 className="text-2xl font-semibold tracking-tight">Selamat Datang di LM-Review!</h2>
                        <p className="text-muted-foreground max-w-md mx-auto">
                            Aplikasi ulasan materi pelatihan. Gunakan menu di sebelah kiri untuk menavigasi aplikasi sesuai dengan peran Anda.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (page: any) => <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>;
