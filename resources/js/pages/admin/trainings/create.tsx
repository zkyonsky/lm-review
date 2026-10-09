import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Create({ statuses = ['Sedang Review', 'Selesai Review'] }: { statuses?: string[] }) {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        title: '',
        description: '',
        status: 'Sedang Review',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/trainings');
    };

    return (
        <>
            <Head title="Tambah Pelatihan" />
            <div className="flex flex-col gap-6 p-6 max-w-2xl mx-auto w-full">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href="/admin/trainings">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Tambah Pelatihan</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Buat pelatihan baru ke dalam sistem.
                        </p>
                    </div>
                </div>

                <Card>
                    <form onSubmit={submit}>
                        <CardHeader>
                            <CardTitle>Informasi Pelatihan</CardTitle>
                            <CardDescription>
                                Masukkan detail pelatihan yang akan diselenggarakan.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="code">Kode Pelatihan</Label>
                                <Input
                                    id="code"
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value)}
                                    placeholder="Contoh: LATSAR-2026"
                                />
                                {errors.code && <p className="text-sm text-destructive">{errors.code}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="title">Judul Pelatihan</Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    placeholder="Contoh: Pelatihan Kepemimpinan"
                                />
                                {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="status">Status Pelatihan</Label>
                                <select
                                    id="status"
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                    className="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {statuses.map((statusOption) => (
                                        <option key={statusOption} value={statusOption}>
                                            {statusOption}
                                        </option>
                                    ))}
                                </select>
                                {errors.status && <p className="text-sm text-destructive">{errors.status}</p>}
                            </div>
                            
                            <div className="space-y-2">
                                <Label htmlFor="description">Deskripsi</Label>
                                <textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Deskripsi singkat mengenai pelatihan ini..."
                                    className="flex min-h-[100px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                {errors.description && <p className="text-sm text-destructive">{errors.description}</p>}
                            </div>
                        </CardContent>
                        <div className="flex items-center justify-end border-t border-border p-4 bg-muted/20">
                            <Button type="submit" disabled={processing}>
                                <Save className="mr-2 h-4 w-4" />
                                Simpan Pelatihan
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </>
    );
}
