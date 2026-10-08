import { Head, Link, usePage } from '@inertiajs/react';
import { BookOpen, Layers, Users, ArrowRight, FileText, Clock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { toast } from 'sonner';
import { useEffect } from 'react';
import type { SharedData } from '@/types';

type User = {
    id: number;
    name: string;
    email: string;
    unit_kerja?: string | null;
};

type Training = {
    id: number;
    title: string;
    code?: string | null;
};

type Subject = {
    id: number;
    title: string;
    code?: string | null;
    jp?: number | null;
    description: string | null;
    materials_count?: number;
    training: Training;
    reviewers: User[];
};

type Props = {
    subjects: Subject[];
};

export default function Index({ subjects = [] }: Props) {
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
            <Head title="Mata Pelatihan Saya" />
            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">Mata Pelatihan Saya</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Daftar mata pelatihan yang ditugaskan kepada Anda untuk pengembangan materi dan konten pembelajaran.
                    </p>
                </div>

                {subjects.length === 0 ? (
                    <Card className="p-12 text-center">
                        <div className="flex flex-col items-center justify-center space-y-3">
                            <div className="p-4 rounded-full bg-muted">
                                <Layers className="h-8 w-8 text-muted-foreground" />
                            </div>
                            <h3 className="text-lg font-semibold">Belum Ada Penugasan Mata Pelatihan</h3>
                            <p className="text-sm text-muted-foreground max-w-sm">
                                Administrator belum menugaskan Anda ke mata pelatihan manapun. Hubungi administrator jika Anda seharusnya memiliki penugasan.
                            </p>
                        </div>
                    </Card>
                ) : (
                    <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {subjects.map((subject) => (
                            <Card key={subject.id} className="flex flex-col justify-between hover:shadow-md transition-shadow">
                                <CardHeader className="pb-3">
                                    <div className="flex items-center justify-between gap-2 mb-1">
                                        <Badge variant="secondary" className="text-xs truncate max-w-[200px]">
                                            {subject.training?.title}
                                        </Badge>
                                        {subject.jp && (
                                            <Badge variant="outline" className="text-xs shrink-0 flex items-center gap-1">
                                                <Clock className="h-3 w-3" />
                                                {subject.jp} JP
                                            </Badge>
                                        )}
                                    </div>
                                    <CardTitle className="text-lg leading-snug line-clamp-2">
                                        {subject.title}
                                    </CardTitle>
                                    {subject.code && (
                                        <p className="text-xs text-muted-foreground font-mono">
                                            Kode: {subject.code}
                                        </p>
                                    )}
                                    {subject.description && (
                                        <CardDescription className="line-clamp-2 text-xs mt-2">
                                            {subject.description}
                                        </CardDescription>
                                    )}
                                </CardHeader>
                                
                                <CardContent className="pt-0 flex flex-col gap-4">
                                    <div className="pt-3 border-t border-border flex items-center justify-between text-xs text-muted-foreground">
                                        <div className="flex items-center gap-1.5">
                                            <FileText className="h-4 w-4 text-blue-500" />
                                            <span><strong>{subject.materials_count ?? 0}</strong> Materi</span>
                                        </div>

                                        <div className="flex items-center gap-1.5">
                                            <Users className="h-4 w-4 text-emerald-500" />
                                            <span><strong>{subject.reviewers?.length ?? 0}</strong> Reviewer</span>
                                        </div>
                                    </div>

                                    {/* Reviewers Avatar Row */}
                                    {subject.reviewers && subject.reviewers.length > 0 && (
                                        <div className="flex items-center gap-2">
                                            <span className="text-[11px] text-muted-foreground">Reviewer:</span>
                                            <div className="flex -space-x-1.5 overflow-hidden">
                                                {subject.reviewers.slice(0, 3).map((reviewer) => (
                                                    <Avatar key={reviewer.id} className="inline-block h-6 w-6 rounded-full ring-2 ring-background" title={reviewer.name}>
                                                        <AvatarFallback className="text-[10px] bg-primary/10 text-primary font-semibold">
                                                            {reviewer.name.slice(0, 2).toUpperCase()}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                ))}
                                                {subject.reviewers.length > 3 && (
                                                    <span className="flex items-center justify-center h-6 w-6 rounded-full bg-muted text-[10px] font-medium text-muted-foreground ring-2 ring-background">
                                                        +{subject.reviewers.length - 3}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    <Button asChild className="w-full mt-2" size="sm">
                                        <Link href={`/developer/subjects/${subject.id}`}>
                                            Kelola Materi
                                            <ArrowRight className="ml-2 h-4 w-4" />
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
