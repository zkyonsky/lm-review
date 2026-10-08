import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Material = {
    id: number;
    title: string;
    description: string | null;
    type: string;
    order: number;
    subject_id: number;
    subject: {
        id: number;
        title: string;
    };
};

type MaterialTypeOption = {
    value: string;
    label?: string;
    name?: string;
};

type Props = {
    material: Material;
    types: (MaterialTypeOption | string)[];
};

export default function Edit({ material, types }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        title: material.title || '',
        description: material.description || '',
        type: material.type || 'pdf',
        order: material.order || 0,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/materials/${material.id}`);
    };

    return (
        <>
            <Head title={`Edit Materi - ${material.title}`} />
            <div className="flex flex-col gap-6 p-6 max-w-2xl mx-auto w-full">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href={`/admin/subjects/${material.subject_id}`}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Edit Materi</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Mata Pelatihan: {material.subject.title}
                        </p>
                    </div>
                </div>

                <Card>
                    <form onSubmit={submit}>
                        <CardHeader>
                            <CardTitle>Informasi Materi</CardTitle>
                            <CardDescription>
                                Perbarui detail materi.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="title">Judul Materi</Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    placeholder="Contoh: Modul 1"
                                />
                                {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="type">Tipe Materi</Label>
                                <select
                                    id="type"
                                    value={data.type}
                                    onChange={(e) => setData('type', e.target.value)}
                                    className="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {types.map((type) => {
                                        const value = typeof type === 'string' ? type : type.value;
                                        const label = typeof type === 'string' ? type.toUpperCase() : (type.label || type.name || type.value);
                                        return (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        );
                                    })}
                                </select>
                                {errors.type && <p className="text-sm text-destructive">{errors.type}</p>}
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
                                <Label htmlFor="order">Urutan</Label>
                                <Input
                                    id="order"
                                    type="number"
                                    min="0"
                                    value={data.order}
                                    onChange={(e) => setData('order', parseInt(e.target.value) || 0)}
                                />
                                {errors.order && <p className="text-sm text-destructive">{errors.order}</p>}
                            </div>
                        </CardContent>
                        <div className="flex items-center justify-end border-t border-border p-4 bg-muted/20">
                            <Button type="submit" disabled={processing}>
                                <Save className="mr-2 h-4 w-4" />
                                Simpan Perubahan
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </>
    );
}
