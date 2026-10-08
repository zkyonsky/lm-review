import { Head, Link, usePage } from '@inertiajs/react';
import { 
    ArrowLeft, Plus, FileDown, Layers, Link as LinkIcon, 
    ExternalLink, MessageSquare, CheckCircle, Clock 
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { toast } from 'sonner';
import { useEffect } from 'react';
import type { SharedData } from '@/types';

type Review = {
    id: number;
    status: string;
    user: {
        id: number;
        name: string;
    };
};

type Version = {
    id: number;
    version_number: number;
    file_path: string | null;
    google_drive_id: string | null;
    is_active: boolean;
    created_at: string;
    reviews: Review[];
};

type Material = {
    id: number;
    title: string;
    description: string | null;
    type: string;
    order: number;
    subject: {
        id: number;
        title: string;
        training: {
            id: number;
            title: string;
        };
    };
    versions: Version[];
};

export default function Show({ material }: { material: Material }) {
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
            <Head title={`Materi: ${material.title}`} />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="icon" asChild>
                            <Link href={`/developer/subjects/${material.subject.id}`}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <p className="text-xs text-muted-foreground font-medium mb-0.5">
                                {material.subject.training.title} &raquo; {material.subject.title}
                            </p>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-bold text-foreground">{material.title}</h1>
                                <Badge variant="outline" className="uppercase text-xs">{material.type}</Badge>
                            </div>
                            {material.description && (
                                <p className="text-sm text-muted-foreground mt-1 max-w-2xl">
                                    {material.description}
                                </p>
                            )}
                        </div>
                    </div>

                    <Button asChild>
                        <Link href={`/developer/materials/${material.id}/versions/create`}>
                            <Plus className="mr-2 h-4 w-4" />
                            Unggah Versi Baru
                        </Link>
                    </Button>
                </div>

                <div className="flex items-center justify-between mt-2">
                    <h2 className="text-lg font-semibold flex items-center">
                        <Layers className="mr-2 h-5 w-5 text-primary" />
                        Daftar Versi &amp; Status Reviu
                    </h2>
                </div>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground border-b border-border">
                                <tr>
                                    <th className="font-medium p-4">Versi</th>
                                    <th className="font-medium p-4">File / Sumber</th>
                                    <th className="font-medium p-4">Status Versi</th>
                                    <th className="font-medium p-4">Reviu dari Reviewer</th>
                                    <th className="font-medium p-4">Tanggal Diunggah</th>
                                    <th className="font-medium p-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {material.versions.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="p-8 text-center text-muted-foreground">
                                            Belum ada versi materi yang diunggah. Silakan klik &quot;Unggah Versi Baru&quot;.
                                        </td>
                                    </tr>
                                ) : (
                                    material.versions.map((version) => (
                                        <tr key={version.id} className="hover:bg-muted/50 transition-colors">
                                            <td className="p-4 font-semibold">
                                                v{version.version_number}
                                            </td>
                                            <td className="p-4">
                                                {version.google_drive_id ? (
                                                    <span className="flex items-center text-xs text-muted-foreground">
                                                        <LinkIcon className="h-3.5 w-3.5 mr-1.5 text-blue-500" /> Google Drive
                                                    </span>
                                                ) : version.file_path ? (
                                                    <span className="flex items-center text-xs text-muted-foreground">
                                                        <FileDown className="h-3.5 w-3.5 mr-1.5 text-emerald-500" /> SCORM Package
                                                    </span>
                                                ) : (
                                                    '-'
                                                )}
                                            </td>
                                            <td className="p-4">
                                                {version.is_active ? (
                                                    <Badge className="bg-emerald-600 hover:bg-emerald-700 text-xs">Aktif</Badge>
                                                ) : (
                                                    <Badge variant="secondary" className="text-xs">Arsip</Badge>
                                                )}
                                            </td>
                                            <td className="p-4">
                                                {version.reviews && version.reviews.length > 0 ? (
                                                    <div className="flex flex-col gap-1.5">
                                                        {version.reviews.map((rev) => (
                                                            <div key={rev.id} className="flex items-center gap-2 text-xs">
                                                                <span className="font-medium">{rev.user?.name}:</span>
                                                                {rev.status === 'submitted' ? (
                                                                    <Badge className="bg-green-600 text-[10px] flex items-center gap-1">
                                                                        <CheckCircle className="h-3 w-3" /> Selesai
                                                                    </Badge>
                                                                ) : (
                                                                    <Badge variant="outline" className="text-[10px] text-amber-600 border-amber-300 flex items-center gap-1">
                                                                        <Clock className="h-3 w-3" /> Sedang Direviu
                                                                    </Badge>
                                                                )}
                                                                <Button asChild size="sm" variant="ghost" className="h-6 px-2 text-[11px]">
                                                                    <Link href={`/developer/workspace/${rev.id}`}>
                                                                        <MessageSquare className="h-3 w-3 mr-1" />
                                                                        Lihat Catatan
                                                                    </Link>
                                                                </Button>
                                                            </div>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground italic">
                                                        Belum ada reviu
                                                    </span>
                                                )}
                                            </td>
                                            <td className="p-4 text-xs text-muted-foreground">
                                                {new Date(version.created_at).toLocaleDateString('id-ID', {
                                                    day: 'numeric',
                                                    month: 'short',
                                                    year: 'numeric'
                                                })}
                                            </td>
                                            <td className="p-4 text-right">
                                                <Button asChild variant="outline" size="sm">
                                                    <a href={`/viewer/${version.id}`} target="_blank" rel="noreferrer">
                                                        <ExternalLink className="h-3.5 w-3.5 mr-1" />
                                                        Pratinjau Materi
                                                    </a>
                                                </Button>
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
