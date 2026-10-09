import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Training = {
    id: number;
    title: string;
};

type Props = {
    training: Training;
    nextOrder?: number;
};

export default function Create({ training, nextOrder = 1 }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        description: '',
        order: nextOrder,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/trainings/${training.id}/subjects`);
    };

    return (
        <>
            <Head title={`Tambah Mata Pelatihan - ${training.title}`} />
            <div className="flex flex-col gap-6 p-6 max-w-2xl mx-auto w-full">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href={`/admin/trainings/${training.id}`}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Tambah Mata Pelatihan</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Pelatihan: {training.title}
                        </p>
                    </div>
                </div>

                <Card>
                    <form onSubmit={submit}>
                        <CardHeader>
                            <CardTitle>Informasi Mata Pelatihan</CardTitle>
                            <CardDescription>
                                Masukkan detail mata pelatihan.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="title">Judul Mata Pelatihan</Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    placeholder="Contoh: Pengenalan Aplikasi"
                                />
                                {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                            </div>
                            
                            <div className="space-y-2">
                                <Label htmlFor="description">Deskripsi</Label>
                                <textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Deskripsi singkat..."
                                    className="flex min-h-[100px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                {errors.description && <p className="text-sm text-destructive">{errors.description}</p>}
                            </div>

                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <Label htmlFor="order">Nomor Urut</Label>
                                    <span className="text-xs text-muted-foreground">Otomatis terisi urutan berikutnya</span>
                                </div>
                                <Input
                                    id="order"
                                    type="number"
                                    min="1"
                                    value={data.order}
                                    onChange={(e) => setData('order', parseInt(e.target.value) || 0)}
                                />
                                {errors.order && <p className="text-sm text-destructive">{errors.order}</p>}
                            </div>
                        </CardContent>
                        <div className="flex items-center justify-end border-t border-border p-4 bg-muted/20">
                            <Button type="submit" disabled={processing}>
                                <Save className="mr-2 h-4 w-4" />
                                Simpan
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </>
    );
}
