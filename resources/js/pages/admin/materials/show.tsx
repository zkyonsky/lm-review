import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Plus, FileDown, Layers, Link as LinkIcon, Eye, Settings, MessageSquare, CheckCircle, Clock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { toast } from 'sonner';
import { useEffect } from 'react';
import type { SharedData } from '@/types';

type Review = {
    id: number;
    status: string;
    user?: {
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
                            <Link href={`/admin/subjects/${material.subject.id}`}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <p className="text-sm text-primary font-medium mb-1">
                                {material.subject.training.title} &raquo; {material.subject.title}
                            </p>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-semibold text-foreground">{material.title}</h1>
                                <Badge variant="outline">{material.type.toUpperCase()}</Badge>
                            </div>
                            <p className="text-sm text-muted-foreground mt-1">
                                {material.description || 'Tidak ada deskripsi.'}
                            </p>
                        </div>
                    </div>
                </div>

                <div className="flex items-center justify-between mt-4">
                    <h2 className="text-xl font-semibold flex items-center">
                        <Layers className="mr-2 h-5 w-5" />
                        Versi Materi
                    </h2>
                    <Button asChild variant="secondary">
                        <Link href={`/admin/materials/${material.id}/versions/create`}>
                            <Plus className="mr-2 h-4 w-4" />
                            Upload / Tambah Versi
                        </Link>
                    </Button>
                </div>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground border-b border-border">
                                <tr>
                                    <th className="font-medium p-4">Versi</th>
                                    <th className="font-medium p-4">File / Link</th>
                                    <th className="font-medium p-4">Status</th>
                                    <th className="font-medium p-4">Catatan Reviewer</th>
                                    <th className="font-medium p-4">Tanggal Diunggah</th>
                                    <th className="font-medium p-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y border-border">
                                {material.versions.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="p-8 text-center text-muted-foreground">
                                            Belum ada versi materi yang diunggah.
                                        </td>
                                    </tr>
                                ) : (
                                    material.versions.map((version) => (
                                        <tr key={version.id} className="hover:bg-muted/50 transition-colors">
                                            <td className="p-4 font-semibold">v{version.version_number}</td>
                                            <td className="p-4">
                                                {version.google_drive_id ? (
                                                    <span className="flex items-center text-muted-foreground">
                                                        <LinkIcon className="h-4 w-4 mr-2 text-blue-500" /> Google Drive
                                                    </span>
                                                ) : version.file_path ? (
                                                    <span className="flex items-center text-muted-foreground">
                                                        <FileDown className="h-4 w-4 mr-2 text-emerald-500" /> File Upload
                                                    </span>
                                                ) : (
                                                    '-'
                                                )}
                                            </td>
                                            <td className="p-4">
                                                {version.is_active ? (
                                                    <Badge className="bg-green-600 hover:bg-green-700">Aktif</Badge>
                                                ) : (
                                                    <Badge variant="secondary">Arsip</Badge>
                                                )}
                                            </td>
                                            <td className="p-4">
                                                {version.reviews && version.reviews.length > 0 ? (
                                                    <div className="flex flex-col gap-1.5">
                                                        {version.reviews.map((rev) => (
                                                            <div key={rev.id} className="flex items-center gap-1.5 text-xs">
                                                                <span className="font-medium">{rev.user?.name || 'Reviewer'}:</span>
                                                                {rev.status === 'submitted' ? (
                                                                    <Badge className="bg-green-600 text-[10px] flex items-center gap-1 py-0 h-4">
                                                                        <CheckCircle className="h-2.5 w-2.5" /> Selesai
                                                                    </Badge>
                                                                ) : (
                                                                    <Badge variant="outline" className="text-[10px] text-amber-600 border-amber-300 flex items-center gap-1 py-0 h-4">
                                                                        <Clock className="h-2.5 w-2.5" /> Draft
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground italic">
                                                        Belum ada reviewer
                                                    </span>
                                                )}
                                            </td>
                                            <td className="p-4 text-muted-foreground text-xs">
                                                {new Date(version.created_at).toLocaleDateString('id-ID', {
                                                    day: 'numeric',
                                                    month: 'short',
                                                    year: 'numeric'
                                                })}
                                            </td>
                                            <td className="p-4 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Button asChild variant="default" size="sm">
                                                        <Link href={`/admin/versions/${version.id}/workspace`}>
                                                            <Eye className="mr-1.5 h-3.5 w-3.5" />
                                                            Lihat Detail
                                                        </Link>
                                                    </Button>
                                                    <Button asChild variant="outline" size="sm" title="Pengaturan Versi & Penugasan Reviewer">
                                                        <Link href={`/admin/versions/${version.id}`}>
                                                            <Settings className="h-3.5 w-3.5" />
                                                        </Link>
                                                    </Button>
                                                </div>
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
