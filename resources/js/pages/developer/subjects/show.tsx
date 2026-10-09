import { Head, Link, usePage } from '@inertiajs/react';
import { 
    ArrowLeft, Plus, MoreVertical, Edit, FileText, 
    Users, Clock, Layers, Upload, ExternalLink, ShieldCheck, Mail, Building, GripVertical 
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { toast } from 'sonner';
import { useEffect } from 'react';
import { cn } from '@/lib/utils';
import { useSortableList } from '@/hooks/use-sortable-list';
import type { SharedData } from '@/types';

type User = {
    id: number;
    name: string;
    email: string;
    nip?: string | null;
    unit_kerja?: string | null;
};

type MaterialVersion = {
    id: number;
    version_number: number;
    created_at: string;
    reviews?: Array<{
        id: number;
        status: string;
        user: { name: string };
    }>;
};

type Material = {
    id: number;
    title: string;
    description: string | null;
    type: string;
    order: number;
    current_version_id?: number | null;
    current_version?: MaterialVersion | null;
    versions: MaterialVersion[];
};

type Training = {
    id: number;
    title: string;
};

type Subject = {
    id: number;
    title: string;
    code?: string | null;
    jp?: number | null;
    description: string | null;
    training: Training;
    materials: Material[];
    developers: User[];
    reviewers: User[];
};

type Props = {
    subject: Subject;
};

export default function Show({ subject }: Props) {
    const { flash } = usePage<{ flash?: { success?: string; error?: string } } & SharedData>().props;

    const {
        items: materials,
        draggedIndex,
        dragOverIndex,
        handleDragStart,
        handleDragOver,
        handleDrop,
        handleDragEnd,
    } = useSortableList<Material>(subject.materials || [], `/developer/subjects/${subject.id}/materials/reorder`);

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
            <Head title={`Mata Pelatihan: ${subject.title}`} />
            <div className="flex flex-col gap-6 p-6">
                {/* Header Navigation */}
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href="/developer/subjects">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                {subject.training?.title}
                            </span>
                            {subject.code && (
                                <Badge variant="outline" className="text-xs">
                                    {subject.code}
                                </Badge>
                            )}
                            {subject.jp && (
                                <Badge variant="secondary" className="text-xs flex items-center gap-1">
                                    <Clock className="h-3 w-3" />
                                    {subject.jp} JP
                                </Badge>
                            )}
                        </div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground mt-0.5">
                            {subject.title}
                        </h1>
                        {subject.description && (
                            <p className="text-sm text-muted-foreground mt-1 max-w-3xl">
                                {subject.description}
                            </p>
                        )}
                    </div>
                </div>

                {/* Team Info Cards */}
                <div className="grid gap-6 md:grid-cols-2">
                    {/* Reviewers Card */}
                    <Card>
                        <CardHeader className="pb-3 border-b border-border/50">
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="text-base flex items-center gap-2">
                                        <ShieldCheck className="h-5 w-5 text-emerald-500" />
                                        Tim Reviewer Ditugaskan
                                    </CardTitle>
                                    <CardDescription className="text-xs mt-0.5">
                                        Reviewer yang akan mengulas materi pada mata pelatihan ini
                                    </CardDescription>
                                </div>
                                <Badge variant="outline" className="text-xs">
                                    {subject.reviewers?.length ?? 0} Reviewer
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="pt-4">
                            {!subject.reviewers || subject.reviewers.length === 0 ? (
                                <p className="text-xs text-muted-foreground italic py-2">
                                    Belum ada reviewer yang ditugaskan oleh administrator.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {subject.reviewers.map((reviewer) => (
                                        <div key={reviewer.id} className="flex items-center justify-between p-2 rounded-lg bg-muted/40 text-xs">
                                            <div className="flex items-center gap-3">
                                                <Avatar className="h-8 w-8">
                                                    <AvatarFallback className="bg-emerald-100 text-emerald-700 text-xs font-semibold">
                                                        {reviewer.name.slice(0, 2).toUpperCase()}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div>
                                                    <div className="font-medium text-foreground">{reviewer.name}</div>
                                                    <div className="text-muted-foreground flex items-center gap-2 mt-0.5 text-[11px]">
                                                        <span className="flex items-center gap-1">
                                                            <Mail className="h-3 w-3" />
                                                            {reviewer.email}
                                                        </span>
                                                        {reviewer.unit_kerja && (
                                                            <span className="flex items-center gap-1">
                                                                <Building className="h-3 w-3" />
                                                                {reviewer.unit_kerja}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            <Badge variant="outline" className="text-[10px] text-emerald-600 border-emerald-300">
                                                Reviewer
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Developers Card */}
                    <Card>
                        <CardHeader className="pb-3 border-b border-border/50">
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="text-base flex items-center gap-2">
                                        <Users className="h-5 w-5 text-blue-500" />
                                        Tim Pengembang Materi
                                    </CardTitle>
                                    <CardDescription className="text-xs mt-0.5">
                                        Pengembang materi yang ditugaskan pada mata pelatihan ini
                                    </CardDescription>
                                </div>
                                <Badge variant="outline" className="text-xs">
                                    {subject.developers?.length ?? 0} Pengembang
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="pt-4">
                            {!subject.developers || subject.developers.length === 0 ? (
                                <p className="text-xs text-muted-foreground italic py-2">
                                    Belum ada pengembang lain.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {subject.developers.map((dev) => (
                                        <div key={dev.id} className="flex items-center justify-between p-2 rounded-lg bg-muted/40 text-xs">
                                            <div className="flex items-center gap-3">
                                                <Avatar className="h-8 w-8">
                                                    <AvatarFallback className="bg-blue-100 text-blue-700 text-xs font-semibold">
                                                        {dev.name.slice(0, 2).toUpperCase()}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div>
                                                    <div className="font-medium text-foreground">{dev.name}</div>
                                                    <div className="text-muted-foreground flex items-center gap-2 mt-0.5 text-[11px]">
                                                        <span className="flex items-center gap-1">
                                                            <Mail className="h-3 w-3" />
                                                            {dev.email}
                                                        </span>
                                                        {dev.unit_kerja && (
                                                            <span className="flex items-center gap-1">
                                                                <Building className="h-3 w-3" />
                                                                {dev.unit_kerja}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            <Badge variant="outline" className="text-[10px] text-blue-600 border-blue-300">
                                                Pengembang
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Materials Management Card */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <div className="flex items-center gap-3">
                                <CardTitle className="text-lg flex items-center gap-2">
                                    <FileText className="h-5 w-5 text-primary" />
                                    Materi Pelatihan
                                </CardTitle>
                                {materials.length > 1 && (
                                    <Badge variant="outline" className="text-xs text-muted-foreground font-normal hidden sm:inline-flex items-center gap-1">
                                        <GripVertical className="h-3 w-3" /> Drag baris untuk ubah urutan
                                    </Badge>
                                )}
                            </div>
                            <CardDescription className="text-xs mt-1">
                                Kelola materi, unggah modul (PDF/Video/SCORM), dan lihat status reviu.
                            </CardDescription>
                        </div>
                        <Button asChild size="sm">
                            <Link href={`/developer/subjects/${subject.id}/materials/create`}>
                                <Plus className="mr-2 h-4 w-4" />
                                Tambah Materi Baru
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {materials.length === 0 ? (
                            <div className="p-8 text-center text-muted-foreground border border-dashed rounded-lg">
                                <p className="text-sm font-medium">Belum ada materi dalam mata pelatihan ini.</p>
                                <p className="text-xs text-muted-foreground mt-1">
                                    Klik tombol &quot;Tambah Materi Baru&quot; di atas untuk menambahkan materi pertama.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground border-b border-border">
                                        <tr>
                                            <th className="w-10 p-3"></th>
                                            <th className="font-medium p-3 w-16">Urutan</th>
                                            <th className="font-medium p-3">Materi</th>
                                            <th className="font-medium p-3">Tipe</th>
                                            <th className="font-medium p-3">Versi Aktif</th>
                                            <th className="font-medium p-3">Total Versi</th>
                                            <th className="font-medium p-3 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {materials.map((mat, index) => {
                                            const activeVersion = mat.versions?.find(v => v.id === mat.current_version_id) || mat.versions?.[0];
                                            return (
                                                <tr
                                                    key={mat.id}
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
                                                    <td className="p-3 text-center cursor-grab active:cursor-grabbing text-muted-foreground/60 group-hover:text-foreground">
                                                        <GripVertical className="h-4 w-4 mx-auto" />
                                                    </td>
                                                    <td className="p-3 font-mono font-medium text-foreground">{mat.order ?? index + 1}</td>
                                                    <td className="p-3">
                                                        <div className="font-medium text-foreground">
                                                            {mat.title}
                                                        </div>
                                                        {mat.description && (
                                                            <div className="text-xs text-muted-foreground line-clamp-1 mt-0.5">
                                                                {mat.description}
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="p-3">
                                                        <Badge variant="outline" className="text-xs uppercase">
                                                            {mat.type}
                                                        </Badge>
                                                    </td>
                                                    <td className="p-3">
                                                        {activeVersion ? (
                                                            <Badge variant="secondary" className="text-xs">
                                                                v{activeVersion.version_number}
                                                            </Badge>
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground italic">
                                                                Belum ada versi
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="p-3 text-xs text-muted-foreground">
                                                        {mat.versions?.length ?? 0} versi
                                                    </td>
                                                    <td className="p-3 text-right">
                                                        <div className="flex items-center justify-end gap-2">
                                                            <Button asChild size="sm" variant="outline">
                                                                <Link href={`/developer/materials/${mat.id}`}>
                                                                    Detail &amp; Versi
                                                                </Link>
                                                            </Button>

                                                            <Button asChild size="sm" variant="secondary">
                                                                <Link href={`/developer/materials/${mat.id}/versions/create`}>
                                                                    <Upload className="h-3.5 w-3.5 mr-1" />
                                                                    Unggah Versi
                                                                </Link>
                                                            </Button>

                                                            <DropdownMenu>
                                                                <DropdownMenuTrigger asChild>
                                                                    <Button variant="ghost" size="icon" className="h-8 w-8">
                                                                        <MoreVertical className="h-4 w-4" />
                                                                    </Button>
                                                                </DropdownMenuTrigger>
                                                                <DropdownMenuContent align="end">
                                                                    <DropdownMenuItem asChild>
                                                                        <Link href={`/developer/materials/${mat.id}/edit`}>
                                                                            <Edit className="mr-2 h-4 w-4" />
                                                                            Edit Info Materi
                                                                        </Link>
                                                                    </DropdownMenuItem>
                                                                </DropdownMenuContent>
                                                            </DropdownMenu>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
