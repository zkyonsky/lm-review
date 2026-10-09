import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Plus, MoreVertical, Edit, Trash, Layers, GripVertical } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { useEffect } from 'react';
import { cn } from '@/lib/utils';
import { useSortableList } from '@/hooks/use-sortable-list';
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

    const {
        items: subjects,
        draggedIndex,
        dragOverIndex,
        handleDragStart,
        handleDragOver,
        handleDrop,
        handleDragEnd,
    } = useSortableList<Subject>(training.subjects || [], `/admin/trainings/${training.id}/subjects/reorder`);

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

                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mt-4">
                    <div className="flex items-center gap-3">
                        <h2 className="text-xl font-semibold flex items-center">
                            <Layers className="mr-2 h-5 w-5" />
                            Daftar Mata Pelatihan
                        </h2>
                        {subjects.length > 1 && (
                            <Badge variant="outline" className="text-xs text-muted-foreground font-normal hidden sm:inline-flex items-center gap-1">
                                <GripVertical className="h-3 w-3" /> Drag baris untuk ubah urutan
                            </Badge>
                        )}
                    </div>
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
                                    <th className="w-10 p-4"></th>
                                    <th className="font-medium p-4 w-20">Urutan</th>
                                    <th className="font-medium p-4">Judul Mata Pelatihan</th>
                                    <th className="font-medium p-4">Deskripsi</th>
                                    <th className="font-medium p-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {subjects.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="p-8 text-center text-muted-foreground">
                                            Belum ada mata pelatihan.
                                        </td>
                                    </tr>
                                ) : (
                                    subjects.map((subject, index) => (
                                        <tr
                                            key={subject.id}
                                            draggable
                                            onDragStart={(e) => handleDragStart(e, index)}
                                            onDragOver={(e) => handleDragOver(e, index)}
                                            onDrop={(e) => handleDrop(e, index)}
                                            onDragEnd={handleDragEnd}
                                            className={cn(
                                                'hover:bg-muted/50 transition-colors group cursor-move',
                                                draggedIndex === index && 'opacity-40 bg-muted/60',
                                                dragOverIndex === index && draggedIndex !== index && 'border-t-2 border-primary bg-primary/5'
                                            )}
                                        >
                                            <td className="p-4 text-center cursor-grab active:cursor-grabbing text-muted-foreground/60 group-hover:text-foreground">
                                                <GripVertical className="h-4 w-4 mx-auto" />
                                            </td>
                                            <td className="p-4 font-mono font-medium text-foreground">{subject.order ?? index + 1}</td>
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
