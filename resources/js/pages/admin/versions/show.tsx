import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Link as LinkIcon, Download, Trash, CheckCircle, Users, UserPlus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import { useEffect, useState } from 'react';
import type { SharedData } from '@/types';

type User = {
    id: number;
    name: string;
};

type Review = {
    id: number;
    status: string;
    user: User;
};

type Version = {
    id: number;
    version_number: number;
    file_path: string | null;
    google_drive_id: string | null;
    is_active: boolean;
    created_at: string;
    preview_url: string;
    material: {
        id: number;
        title: string;
        type: string;
        subject: {
            title: string;
            training: {
                title: string;
            };
        };
    };
    scorm_package?: {
        version: string;
        status: string;
        scos: any[];
    };
    reviews: Review[];
};

type Props = {
    version: Version;
    availableReviewers: User[];
};

export default function Show({ version, availableReviewers }: Props) {
    const { flash } = usePage<{ flash?: { success?: string; error?: string } } & SharedData>().props;
    const { patch, delete: destroy, post, processing, data, setData } = useForm({
        user_id: ''
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    const handleToggleActive = () => {
        if (!version.is_active) {
            patch(`/admin/versions/${version.id}/toggle`);
        }
    };

    const handleDelete = () => {
        if (confirm('Yakin ingin menghapus versi ini?')) {
            destroy(`/admin/versions/${version.id}`);
        }
    };

    const handleAssign = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.user_id) {
            toast.error('Silakan pilih reviewer terlebih dahulu.');
            return;
        }
        post(`/admin/versions/${version.id}/assign`, {
            onSuccess: () => setData('user_id', '')
        });
    };

    const handleUnassign = (reviewId: number) => {
        if (confirm('Cabut penugasan reviewer ini?')) {
            destroy(`/admin/versions/${version.id}/assign/${reviewId}`);
        }
    };

    return (
        <>
            <Head title={`Detail Versi v${version.version_number}`} />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="icon" asChild>
                            <Link href={`/admin/materials/${version.material.id}`}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <p className="text-sm text-primary font-medium mb-1">
                                {version.material.subject.training.title} &raquo; {version.material.subject.title} &raquo; {version.material.title} ({version.material.type.toUpperCase()})
                            </p>
                            <h1 className="text-2xl font-semibold text-foreground flex items-center gap-2">
                                Versi {version.version_number}
                                {version.is_active ? (
                                    <Badge className="bg-green-500">Aktif</Badge>
                                ) : (
                                    <Badge variant="secondary">Arsip</Badge>
                                )}
                            </h1>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {!version.is_active && (
                            <Button variant="outline" onClick={handleToggleActive}>
                                <CheckCircle className="mr-2 h-4 w-4" />
                                Jadikan Aktif
                            </Button>
                        )}
                        <Button variant="destructive" onClick={handleDelete}>
                            <Trash className="mr-2 h-4 w-4" />
                            Hapus
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Pratinjau Materi</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="aspect-video w-full rounded-md border border-border overflow-hidden bg-muted flex items-center justify-center">
                                {version.material.type === 'pdf' || version.material.type === 'video' ? (
                                    <iframe 
                                        src={version.preview_url} 
                                        className="w-full h-full border-0" 
                                        allow="autoplay"
                                    />
                                ) : (
                                    <div className="text-center p-6">
                                        <p className="mb-4">Pratinjau SCORM akan tersedia di modul Viewer khusus.</p>
                                        <Button asChild>
                                            <a href={`/viewer/${version.id}`} target="_blank" rel="noreferrer">
                                                Buka SCORM Viewer
                                            </a>
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Informasi Versi</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">Tipe File</p>
                                    <p className="font-medium">{version.material.type.toUpperCase()}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Waktu Unggah</p>
                                    <p className="font-medium">{new Date(version.created_at).toLocaleString('id-ID')}</p>
                                </div>
                                
                                {version.google_drive_id && (
                                    <div>
                                        <p className="text-sm text-muted-foreground">Tautan Eksternal</p>
                                        <a 
                                            href={`https://drive.google.com/file/d/${version.google_drive_id}/view`} 
                                            target="_blank" 
                                            rel="noreferrer"
                                            className="text-primary hover:underline flex items-center mt-1"
                                        >
                                            <LinkIcon className="mr-2 h-4 w-4" />
                                            Buka di Google Drive
                                        </a>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center">
                                    <Users className="mr-2 h-5 w-5" />
                                    Reviewer
                                </CardTitle>
                                <CardDescription>Tugaskan reviewer untuk mengevaluasi versi ini.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleAssign} className="flex gap-2 mb-6">
                                    <div className="flex-1">
                                        <select
                                            className="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                            value={data.user_id}
                                            onChange={(e) => setData('user_id', e.target.value)}
                                        >
                                            <option value="">-- Pilih Reviewer --</option>
                                            {availableReviewers.map(r => (
                                                <option key={r.id} value={r.id}>{r.name}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <Button type="submit" disabled={processing || !data.user_id}>
                                        <UserPlus className="h-4 w-4" />
                                    </Button>
                                </form>

                                <div className="space-y-3">
                                    <h4 className="text-sm font-semibold text-muted-foreground uppercase">Reviewer Ditugaskan</h4>
                                    {version.reviews.length === 0 ? (
                                        <p className="text-sm text-muted-foreground italic">Belum ada penugasan.</p>
                                    ) : (
                                        version.reviews.map(review => (
                                            <div key={review.id} className="flex items-center justify-between p-3 rounded-md border border-border bg-muted/20">
                                                <div>
                                                    <p className="font-medium text-sm">{review.user.name}</p>
                                                    <Badge variant={review.status === 'submitted' ? 'default' : 'secondary'} className="mt-1 text-[10px]">
                                                        {review.status.toUpperCase()}
                                                    </Badge>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={`/admin/workspace/${review.id}`}>
                                                            Buka Workspace
                                                        </Link>
                                                    </Button>
                                                    <Button 
                                                        variant="ghost" 
                                                        size="icon" 
                                                        className="h-8 w-8 text-destructive hover:text-destructive hover:bg-destructive/10"
                                                        onClick={() => handleUnassign(review.id)}
                                                    >
                                                        <Trash className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
