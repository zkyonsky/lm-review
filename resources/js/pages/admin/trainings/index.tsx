import { Head, Link, usePage } from '@inertiajs/react';
import { BookOpen, Plus, MoreVertical, Edit, Trash } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import { toast } from 'sonner';
import { useEffect } from 'react';
import type { SharedData } from '@/types';

type Training = {
    id: number;
    code: string;
    title: string;
    description: string | null;
    created_at: string;
};

type Props = {
    trainings: {
        data: Training[];
        links: any[];
    };
};

export default function Index({ trainings }: Props) {
    const { flash } = usePage<{ flash?: { success?: string; error?: string } } & SharedData>().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    return (
        <>
            <Head title="Manajemen Pelatihan" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Daftar Pelatihan</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Kelola data pelatihan, mata pelatihan, dan materi.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/admin/trainings/create">
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah Pelatihan
                        </Link>
                    </Button>
                </div>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground border-b border-border">
                                <tr>
                                    <th className="font-medium p-4">Kode</th>
                                    <th className="font-medium p-4">Judul Pelatihan</th>
                                    <th className="font-medium p-4">Deskripsi</th>
                                    <th className="font-medium p-4">Tanggal Dibuat</th>
                                    <th className="font-medium p-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {trainings.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="p-8 text-center text-muted-foreground">
                                            Belum ada data pelatihan.
                                        </td>
                                    </tr>
                                ) : (
                                    trainings.data.map((training) => (
                                        <tr key={training.id} className="hover:bg-muted/50 transition-colors">
                                            <td className="p-4 align-middle">
                                                <Badge variant="outline" className="font-mono">{training.code}</Badge>
                                            </td>
                                            <td className="p-4">
                                                <Link href={`/admin/trainings/${training.id}`} className="font-medium text-primary hover:underline">
                                                    {training.title}
                                                </Link>
                                            </td>
                                            <td className="p-4 text-muted-foreground max-w-md truncate">
                                                {training.description || '-'}
                                            </td>
                                            <td className="p-4 text-muted-foreground">
                                                {new Date(training.created_at).toLocaleDateString('id-ID')}
                                            </td>
                                            <td className="p-4 text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="icon">
                                                            <MoreVertical className="h-4 w-4" />
                                                            <span className="sr-only">Menu</span>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem asChild>
                                                            <Link href={`/admin/trainings/${training.id}`}>
                                                                <BookOpen className="mr-2 h-4 w-4" />
                                                                Detail & Materi
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild>
                                                            <Link href={`/admin/trainings/${training.id}/edit`}>
                                                                <Edit className="mr-2 h-4 w-4" />
                                                                Edit
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild>
                                                            <Link 
                                                                href={`/admin/trainings/${training.id}`}
                                                                method="delete"
                                                                as="button"
                                                                className="text-destructive focus:text-destructive w-full"
                                                            >
                                                                <Trash className="mr-2 h-4 w-4" />
                                                                Hapus
                                                            </Link>
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </>
    );
}
