import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, UploadCloud, FileArchive } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';

type Material = {
    id: number;
    title: string;
    type: string;
};

function formatBytes(bytes?: number): string {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${(bytes / Math.pow(k, i)).toFixed(1)} ${sizes[i]}`;
}

export default function Create({ material }: { material: Material }) {
    const { data, setData, post, processing, progress, errors } = useForm({
        google_drive_id: '',
        scorm_file: null as File | null,
        is_active: true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/materials/${material.id}/versions`, {
            forceFormData: true,
        });
    };

    return (
        <>
            <Head title={`Unggah Versi Materi - ${material.title}`} />
            <div className="flex flex-col gap-6 p-6 max-w-2xl mx-auto w-full">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href={`/admin/materials/${material.id}`}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Versi Baru</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Materi: {material.title} ({material.type.toUpperCase()})
                        </p>
                    </div>
                </div>

                <Card>
                    <form onSubmit={submit}>
                        <CardHeader>
                            <CardTitle>File Materi</CardTitle>
                            <CardDescription>
                                {material.type === 'scorm' 
                                    ? 'Unggah file paket SCORM (ZIP).' 
                                    : 'Masukkan Link Google Drive atau Google Drive ID.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {material.type === 'scorm' ? (
                                <div className="space-y-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="scorm_file">File SCORM (.zip)</Label>
                                        <Input
                                            id="scorm_file"
                                            type="file"
                                            accept=".zip"
                                            disabled={processing}
                                            onChange={(e) => setData('scorm_file', e.target.files?.[0] || null)}
                                        />
                                        {errors.scorm_file && <p className="text-sm text-destructive">{errors.scorm_file}</p>}
                                    </div>

                                    {data.scorm_file && !processing && (
                                        <div className="flex items-center justify-between text-xs text-muted-foreground bg-muted/40 p-2.5 rounded-md border border-border">
                                            <span className="flex items-center gap-2 truncate">
                                                <FileArchive className="h-4 w-4 text-primary shrink-0" />
                                                <span className="truncate font-medium text-foreground">{data.scorm_file.name}</span>
                                            </span>
                                            <span className="shrink-0 ml-2 font-mono text-xs">{formatBytes(data.scorm_file.size)}</span>
                                        </div>
                                    )}

                                    {processing && (
                                        <div className="rounded-lg border border-primary/20 bg-primary/5 p-4 space-y-3 animate-in fade-in">
                                            <div className="flex items-center justify-between text-xs">
                                                <span className="font-medium text-foreground flex items-center gap-2">
                                                    <Spinner className="h-4 w-4 text-primary" />
                                                    {(progress?.percentage ?? 100) < 100
                                                        ? 'Mengunggah paket SCORM...'
                                                        : 'Memproses & mengekstrak paket SCORM di server...'}
                                                </span>
                                                <span className="font-semibold text-primary">
                                                    {progress?.percentage ?? 100}%
                                                </span>
                                            </div>
                                            <Progress value={progress?.percentage ?? 100} />
                                            <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                                <span>
                                                    {progress?.loaded && progress?.total
                                                        ? `${formatBytes(progress.loaded)} / ${formatBytes(progress.total)}`
                                                        : data.scorm_file
                                                        ? formatBytes(data.scorm_file.size)
                                                        : ''}
                                                </span>
                                                <span>
                                                    {(progress?.percentage ?? 100) < 100
                                                        ? 'Mohon jangan menutup halaman ini'
                                                        : 'Ekstraksi SCORM sedang berjalan...'}
                                                </span>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <Label htmlFor="google_drive_id">Google Drive ID / Tautan</Label>
                                    <Input
                                        id="google_drive_id"
                                        value={data.google_drive_id}
                                        disabled={processing}
                                        onChange={(e) => setData('google_drive_id', e.target.value)}
                                        placeholder="Contoh: 1aB2c... atau https://drive.google.com/file/d/.../view"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Pastikan tautan dapat diakses publik (Anyone with the link can view).
                                    </p>
                                    {errors.google_drive_id && <p className="text-sm text-destructive">{errors.google_drive_id}</p>}
                                </div>
                            )}

                            <div className="space-y-3 pt-4">
                                <div className="flex items-center space-x-2">
                                    <Checkbox 
                                        id="is_active" 
                                        checked={data.is_active}
                                        disabled={processing}
                                        onCheckedChange={(checked) => setData('is_active', checked as boolean)}
                                    />
                                    <Label htmlFor="is_active" className="cursor-pointer">
                                        Jadikan Versi Aktif (Versi lain otomatis dinonaktifkan)
                                    </Label>
                                </div>
                                {errors.is_active && <p className="text-sm text-destructive">{errors.is_active}</p>}
                            </div>
                        </CardContent>
                        <div className="flex items-center justify-end border-t border-border p-4 bg-muted/20">
                            <Button type="submit" disabled={processing}>
                                {processing ? (
                                    <>
                                        <Spinner className="mr-2 h-4 w-4" />
                                        {material.type === 'scorm' 
                                            ? ((progress?.percentage ?? 100) < 100 ? `Mengunggah (${progress?.percentage}%)...` : 'Memproses...') 
                                            : 'Menyimpan...'}
                                    </>
                                ) : (
                                    <>
                                        <UploadCloud className="mr-2 h-4 w-4" />
                                        {material.type === 'scorm' ? 'Unggah SCORM' : 'Simpan Link'}
                                    </>
                                )}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </>
    );
}
