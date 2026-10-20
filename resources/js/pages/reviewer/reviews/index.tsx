import { Head, Link, usePage } from '@inertiajs/react';
import { BookOpen, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import Pagination, { type PaginationLink } from '@/components/pagination';
import { toast } from 'sonner';
import { useEffect } from 'react';
import type { SharedData } from '@/types';

type Review = {
    id: number;
    status: string;
    created_at: string;
    material_version?: {
        id: number;
        version_number: number;
        changelog?: string | null;
        material?: {
            title?: string;
            type?: string;
            subject?: {
                title?: string;
                training?: {
                    title?: string;
                };
            };
        };
    } | null;
};

type Props = {
    reviews: {
        data: Review[];
        current_page?: number;
        from?: number | null;
        last_page?: number;
        links?: PaginationLink[];
        per_page?: number;
        to?: number | null;
        total?: number;
    };
};

export default function Index({ reviews }: Props) {
    const { flash } = usePage<{ flash?: { success?: string; error?: string } } & SharedData>().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    const reviewItems = reviews?.data ?? [];

    return (
        <>
            <Head title="Tugas Reviu" />
            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">Tugas Reviu</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Daftar materi pelatihan pada periode reviu aktif yang ditugaskan kepada Anda untuk direviu.
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
                                {reviewItems.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="p-8 text-center text-muted-foreground">
                                            Belum ada tugas reviu pada periode aktif saat ini.
                                        </td>
                                    </tr>
                                ) : (
                                    reviewItems.map((review) => {
                                        const version = review.material_version;
                                        const material = version?.material;
                                        const subject = material?.subject;
                                        const training = subject?.training;

                                        const materialTitle = material?.title ?? 'Materi Pembelajaran';
                                        const versionText = version?.version_number ? `(v${version.version_number})` : '';
                                        const materialType = material?.type ? material.type.toUpperCase() : 'BERKAS';
                                        const trainingTitle = training?.title ?? 'Pelatihan';
                                        const subjectTitle = subject?.title ?? '-';

                                        const isDraft = review.status === 'draft';

                                        return (
                                            <tr key={review.id} className="hover:bg-muted/50 transition-colors">
                                                <td className="p-4">
                                                    <div className="font-medium text-foreground flex items-center gap-2">
                                                        <span>{materialTitle}</span>
                                                        {versionText && (
                                                            <span className="text-xs text-muted-foreground font-normal">
                                                                {versionText}
                                                            </span>
                                                        )}
                                                        {version?.version_number && version.version_number > 1 && (
                                                            <Badge variant="outline" className="text-[10px] bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/40 dark:text-purple-300">
                                                                Pemutakhiran
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground flex items-center gap-2 mt-1">
                                                        <Badge variant="outline" className="text-[10px]">
                                                            {materialType}
                                                        </Badge>
                                                        {version?.changelog && (
                                                            <span className="text-[11px] text-muted-foreground line-clamp-1 italic">
                                                                Perubahan: {version.changelog}
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="p-4">
                                                    <div className="font-medium text-sm text-foreground">
                                                        {trainingTitle}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground mt-0.5">
                                                        {subjectTitle}
                                                    </div>
                                                </td>
                                                <td className="p-4">
                                                    {isDraft ? (
                                                        <Badge variant="secondary" className="bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                                                            Perlu Direviu
                                                        </Badge>
                                                    ) : (
                                                        <Badge className="bg-emerald-600 hover:bg-emerald-700 text-white">
                                                            Selesai
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="p-4 text-right">
                                                    <Button asChild size="sm" variant={isDraft ? 'default' : 'outline'}>
                                                        <Link href={`/reviewer/workspace/${review.id}`}>
                                                            {isDraft ? (
                                                                <>
                                                                    <BookOpen className="mr-1.5 h-4 w-4" /> Buka Workspace
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <ExternalLink className="mr-1.5 h-4 w-4" /> Lihat Ulasan
                                                                </>
                                                            )}
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {reviews?.links && (
                        <Pagination
                            links={reviews.links}
                            from={reviews.from}
                            to={reviews.to}
                            total={reviews.total}
                            itemName="tugas reviu"
                        />
                    )}
                </Card>
            </div>
        </>
    );
}
