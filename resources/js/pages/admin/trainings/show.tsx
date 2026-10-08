import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Plus, MoreVertical, Edit, Trash, Layers } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { useEffect } from 'react';
import type { SharedData } from '@/types';

type Subject = {
    id: number;
    title: string;
    description: string | null;
    order: number;
};

type Training = {
    id: number;
    code: string;
    title: string;
    description: string | null;
    created_at: string;
    subjects: Subject[];
};

export default function Show({ training }: { training: Training }) {
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
            <Head title={`Pelatihan: ${training.title}`} />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="icon" asChild>
                            <Link href="/admin/trainings">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-semibold text-foreground">{training.title}</h1>
                                <Badge variant="outline" className="font-mono">{training.code}</Badge>
                            </div>
                            <p className="text-sm text-muted-foreground mt-1">
                                {training.description || 'Tidak ada deskripsi.'}
                            </p>
                        </div>
                    </div>
                </div>

                <div className="flex items-center justify-between mt-4">
                    <h2 className="text-xl font-semibold flex items-center">
                        <Layers className="mr-2 h-5 w-5" />
                        Daftar Mata Pelatihan
                    </h2>
                    <Button asChild>
                        <Link href={`/admin/trainings/${training.id}/subjects/create`}>
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah Mata Pelatihan
                        </Link>
                    </Button>
                </div>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground border-b border-border">
                                <tr>
                                    <th className="font-medium p-4">Urutan</th>
                                    <th className="font-medium p-4">Judul Mata Pelatihan</th>
                                    <th className="font-medium p-4">Deskripsi</th>
                                    <th className="font-medium p-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {training.subjects.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="p-8 text-center text-muted-foreground">
                                            Belum ada mata pelatihan.
                                        </td>
                                    </tr>
                                ) : (
                                    training.subjects.map((subject) => (
                                        <tr key={subject.id} className="hover:bg-muted/50 transition-colors">
                                            <td className="p-4">{subject.order}</td>
                                            <td className="p-4">
                                                <Link href={`/admin/subjects/${subject.id}`} className="font-medium text-primary hover:underline">
                                                    {subject.title}
                                                </Link>
                                            </td>
                                            <td className="p-4 text-muted-foreground max-w-md truncate">
                                                {subject.description || '-'}
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
                                                            <Link href={`/admin/subjects/${subject.id}`}>
                                                                <Layers className="mr-2 h-4 w-4" />
                                                                Detail & Materi
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild>
                                                            <Link href={`/admin/subjects/${subject.id}/edit`}>
                                                                <Edit className="mr-2 h-4 w-4" />
                                                                Edit
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild>
                                                            <Link 
                                                                href={`/admin/subjects/${subject.id}`}
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
