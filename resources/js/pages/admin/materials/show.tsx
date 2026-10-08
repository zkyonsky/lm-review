import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Plus, MoreVertical, FileDown, Layers, Link as LinkIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { toast } from 'sonner';
import { useEffect } from 'react';
import type { SharedData } from '@/types';

type Version = {
    id: number;
    version_number: number;
    file_path: string | null;
    google_drive_id: string | null;
    is_active: boolean;
    created_at: string;
    reviews: any[];
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
                                    <th className="font-medium p-4">Tanggal Diunggah</th>
                                    <th className="font-medium p-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {material.versions.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="p-8 text-center text-muted-foreground">
                                            Belum ada versi materi yang diunggah.
                                        </td>
                                    </tr>
                                ) : (
                                    material.versions.map((version) => (
                                        <tr key={version.id} className="hover:bg-muted/50 transition-colors">
                                            <td className="p-4 font-medium">v{version.version_number}</td>
                                            <td className="p-4">
                                                {version.google_drive_id ? (
                                                    <span className="flex items-center text-muted-foreground">
                                                        <LinkIcon className="h-4 w-4 mr-2" /> Google Drive
                                                    </span>
                                                ) : version.file_path ? (
                                                    <span className="flex items-center text-muted-foreground">
                                                        <FileDown className="h-4 w-4 mr-2" /> File Upload
                                                    </span>
                                                ) : (
                                                    '-'
                                                )}
                                            </td>
                                            <td className="p-4">
                                                {version.is_active ? (
                                                    <Badge variant="default" className="bg-green-500 hover:bg-green-600">Aktif</Badge>
                                                ) : (
                                                    <Badge variant="secondary">Arsip</Badge>
                                                )}
                                            </td>
                                            <td className="p-4 text-muted-foreground">
                                                {new Date(version.created_at).toLocaleDateString('id-ID')}
                                            </td>
                                            <td className="p-4 text-right">
                                                <Button variant="ghost" size="sm">
                                                    Lihat Detail
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
