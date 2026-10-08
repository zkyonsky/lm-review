import { Head, Link, usePage } from '@inertiajs/react';
import { BookOpen, CheckCircle, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import type { SharedData } from '@/types';

type Review = {
    id: number;
    status: string;
    created_at: string;
    material_version: {
        id: number;
        version_number: number;
        material: {
            title: string;
            type: string;
            subject: {
                title: string;
                training: {
                    title: string;
                };
            };
        };
    };
};

type Props = {
    reviews: {
        data: Review[];
        links: any[];
    };
};

export default function Index({ reviews }: Props) {
    return (
        <>
            <Head title="Tugas Reviu" />
            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">Tugas Reviu</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Daftar materi pelatihan yang ditugaskan kepada Anda untuk direviu.
                    </p>
                </div>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground border-b border-border">
                                <tr>
                                    <th className="font-medium p-4">Materi</th>
                                    <th className="font-medium p-4">Pelatihan & Modul</th>
                                    <th className="font-medium p-4">Status</th>
                                    <th className="font-medium p-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {reviews.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="p-8 text-center text-muted-foreground">
                                            Belum ada tugas reviu.
                                        </td>
                                    </tr>
                                ) : (
                                    reviews.data.map((review) => (
                                        <tr key={review.id} className="hover:bg-muted/50 transition-colors">
                                            <td className="p-4">
                                                <div className="font-medium text-foreground">
                                                    {review.material_version.material.title} 
                                                    <span className="text-xs ml-2 text-muted-foreground">(v{review.material_version.version_number})</span>
                                                </div>
                                                <div className="text-xs text-muted-foreground flex gap-2 mt-1">
                                                    <Badge variant="outline" className="text-[10px]">
                                                        {review.material_version.material.type.toUpperCase()}
                                                    </Badge>
                                                </div>
                                            </td>
                                            <td className="p-4">
                                                <div className="font-medium text-sm">
                                                    {review.material_version.material.subject.training.title}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {review.material_version.material.subject.title}
                                                </div>
                                            </td>
                                            <td className="p-4">
                                                {review.status === 'draft' ? (
                                                    <Badge variant="secondary">Perlu Direviu</Badge>
                                                ) : (
                                                    <Badge className="bg-green-500">Selesai</Badge>
                                                )}
                                            </td>
                                            <td className="p-4 text-right">
                                                <Button asChild size="sm" variant={review.status === 'draft' ? 'default' : 'outline'}>
                                                    <Link href={`/reviewer/workspace/${review.id}`}>
                                                        {review.status === 'draft' ? (
                                                            <>
                                                                <BookOpen className="mr-2 h-4 w-4" /> Buka Workspace
                                                            </>
                                                        ) : (
                                                            <>
                                                                <ExternalLink className="mr-2 h-4 w-4" /> Lihat Ulasan
                                                            </>
                                                        )}
                                                    </Link>
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
