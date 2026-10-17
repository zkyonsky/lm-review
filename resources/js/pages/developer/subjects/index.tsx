import { Head, Link, router, usePage } from '@inertiajs/react';
import { 
    Layers, 
    ArrowRight, 
    FileText, 
    Clock, 
    CheckCircle2, 
    Search, 
    X, 
    RotateCcw, 
    Users,
    GraduationCap
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import Pagination, { type PaginationLink } from '@/components/pagination';
import { toast } from 'sonner';
import { useEffect, useState } from 'react';
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
    status: string;
};

type Subject = {
    id: number;
    title: string;
    materials_count?: number;
    reviewed_materials_count?: number;
    training: Training;
    reviewers: User[];
};

type Props = {
    subjects: {
        data: Subject[];
        current_page: number;
        from: number | null;
        last_page: number;
        links: PaginationLink[];
        per_page: number;
        to: number | null;
        total: number;
    };
    filters?: {
        search?: string;
        status?: string;
        period_id?: string;
    };
    statuses?: string[];
    periods?: Array<{ id: number; name: string; year: number; status: string }>;
};

export default function Index({ 
    subjects, 
    filters = {}, 
    statuses = ['Sedang Review', 'Selesai Review'],
    periods = []
}: Props) {
    const { flash } = usePage<{ flash?: { success?: string; error?: string; warning?: string } } & SharedData>().props;
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [periodFilter, setPeriodFilter] = useState(filters.period_id || '');
    const [isUpdatingStatus, setIsUpdatingStatus] = useState(false);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
        if (flash?.warning) {
            toast.warning(flash.warning);
        }
    }, [flash]);

    useEffect(() => {
        setSearchTerm(filters.search || '');
        setStatusFilter(filters.status || '');
    }, [filters.search, filters.status]);

    const isAllSelected = subjects.data.length > 0 && subjects.data.every((s) => selectedIds.includes(s.id));

    const toggleSelectAll = () => {
        if (isAllSelected) {
            setSelectedIds([]);
        } else {
            setSelectedIds(subjects.data.map((s) => s.id));
        }
    };

    const toggleSelect = (id: number) => {
        setSelectedIds((prev) =>
            prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]
        );
    };

    const applyFilter = (newSearch: string, newStatus: string, newPeriod: string = periodFilter) => {
        router.get(
            '/developer/subjects',
            {
                search: newSearch || undefined,
                status: newStatus || undefined,
                period_id: newPeriod || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilter(searchTerm, statusFilter, periodFilter);
    };

    const handleStatusFilterChange = (val: string) => {
        setStatusFilter(val);
        applyFilter(searchTerm, val, periodFilter);
    };

    const handlePeriodFilterChange = (val: string) => {
        setPeriodFilter(val);
        applyFilter(searchTerm, statusFilter, val);
    };

    const handleResetFilter = () => {
        setSearchTerm('');
        setStatusFilter('');
        setPeriodFilter('');
        router.get('/developer/subjects', {}, { preserveState: true, replace: true });
    };

    const handleBulkStatusChange = (newStatus: string) => {
        if (selectedIds.length === 0) {
            toast.error('Pilih setidaknya satu mata pelatihan terlebih dahulu.');
            return;
        }

        setIsUpdatingStatus(true);
        router.post(
            '/developer/trainings/status/bulk',
            {
                subject_ids: selectedIds,
                status: newStatus,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedIds([]);
                    setIsUpdatingStatus(false);
                },
                onError: () => {
                    setIsUpdatingStatus(false);
                },
            }
        );
    };

    const hasActiveFilter = Boolean(filters.search || filters.status || filters.period_id);

    return (
        <>
            <Head title="Mata Pelatihan Saya" />
            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Mata Pelatihan Saya</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Daftar mata pelatihan yang ditugaskan kepada Anda untuk pengembangan materi dan konten pembelajaran.
                        </p>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="flex flex-col md:flex-row items-stretch md:items-center gap-3">
                    <form onSubmit={handleSearchSubmit} className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
                        <Input
                            placeholder="Cari nama pelatihan atau mata pelatihan..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="pl-9 pr-8 h-10 w-full bg-card"
                        />
                        {searchTerm && (
                            <button
                                type="button"
                                onClick={() => {
                                    setSearchTerm('');
                                    applyFilter('', statusFilter);
                                }}
                                className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        )}
                    </form>

                    <div className="flex flex-wrap items-center gap-2">
                        {periods && periods.length > 0 && (
                            <select
                                value={periodFilter}
                                onChange={(e) => handlePeriodFilterChange(e.target.value)}
                                aria-label="Filter periode reviu"
                                className="h-10 min-w-[190px] rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                            >
                                <option value="">Semua Periode</option>
                                {periods.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name} ({p.year})
                                    </option>
                                ))}
                            </select>
                        )}

                        <select
                            value={statusFilter}
                            onChange={(e) => handleStatusFilterChange(e.target.value)}
                            aria-label="Filter status review pelatihan"
                            className="h-10 min-w-[170px] rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                        >
                            <option value="">Semua Status</option>
                            {statuses.map((s) => (
                                <option key={s} value={s}>
                                    {s}
                                </option>
                            ))}
                        </select>

                        <Button 
                            type="button" 
                            variant="secondary" 
                            onClick={() => applyFilter(searchTerm, statusFilter)}
                            className="h-10 px-4"
                        >
                            Cari
                        </Button>

                        {hasActiveFilter && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={handleResetFilter}
                                title="Reset filter"
                                className="h-10 px-3 text-muted-foreground hover:text-foreground gap-1"
                            >
                                <RotateCcw className="h-4 w-4" />
                                <span className="hidden sm:inline text-xs">Reset</span>
                            </Button>
                        )}
                    </div>
                </div>



                {/* Table Card */}
                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground border-b border-border">
                                <tr>
                                    <th className="w-10 p-4">
                                        <Checkbox
                                            checked={isAllSelected}
                                            onCheckedChange={toggleSelectAll}
                                            aria-label="Pilih semua mata pelatihan"
                                        />
                                    </th>
                                    <th className="font-medium p-4">Mata Pelatihan</th>
                                    <th className="font-medium p-4 w-40">Status Reviu</th>
                                    <th className="font-medium p-4 w-44 text-center">Materi Direviu</th>
                                    <th className="font-medium p-4">Reviewer</th>
                                    <th className="font-medium p-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {subjects.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="p-12 text-center text-muted-foreground">
                                            <div className="flex flex-col items-center justify-center space-y-3">
                                                <div className="p-3 rounded-full bg-muted">
                                                    <Layers className="h-6 w-6 text-muted-foreground" />
                                                </div>
                                                <div className="text-base font-medium text-foreground">
                                                    {hasActiveFilter
                                                        ? 'Tidak ada mata pelatihan yang cocok dengan filter pencarian.'
                                                        : 'Belum Ada Penugasan Mata Pelatihan'}
                                                </div>
                                                <p className="text-xs text-muted-foreground max-w-sm">
                                                    {hasActiveFilter
                                                        ? 'Coba gunakan kata kunci pencarian lain atau reset filter status.'
                                                        : 'Administrator belum menugaskan Anda ke mata pelatihan manapun.'}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    subjects.data.map((subject) => {
                                        const isDone = subject.training?.status === 'Selesai Review';
                                        const isSelected = selectedIds.includes(subject.id);

                                        return (
                                            <tr 
                                                key={subject.id} 
                                                className={`hover:bg-muted/50 transition-colors ${
                                                    isSelected ? 'bg-muted/30' : ''
                                                }`}
                                            >
                                                <td className="p-4 align-middle">
                                                    <Checkbox
                                                        checked={isSelected}
                                                        onCheckedChange={() => toggleSelect(subject.id)}
                                                        aria-label={`Pilih ${subject.title}`}
                                                    />
                                                </td>
                                                <td className="p-4">
                                                    <div className="flex flex-col gap-1">
                                                        <Link 
                                                            href={`/developer/subjects/${subject.id}`}
                                                            className="font-medium text-foreground hover:text-primary hover:underline text-sm leading-snug break-words"
                                                        >
                                                            {subject.title}
                                                        </Link>
                                                        <span className="text-xs text-muted-foreground leading-normal break-words">
                                                            {subject.training?.title}
                                                            {subject.training?.code ? ` (${subject.training.code})` : ''}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="p-4 align-middle">
                                                    {(() => {
                                                        const total = subject.materials_count ?? 0;
                                                        const reviewed = subject.reviewed_materials_count ?? 0;
                                                        if (total === 0) {
                                                            return (
                                                                <Badge variant="outline" className="border-muted-foreground/30 bg-muted/50 text-muted-foreground gap-1.5 py-1 px-2.5 font-normal text-xs">
                                                                    <Clock className="h-3.5 w-3.5" />
                                                                    <span>Belum Ada Materi</span>
                                                                </Badge>
                                                            );
                                                        }
                                                        if (reviewed >= total) {
                                                            return (
                                                                <Badge variant="outline" className="border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400 gap-1.5 py-1 px-2.5 font-medium text-xs">
                                                                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                                                                    <span>Selesai Reviu</span>
                                                                </Badge>
                                                            );
                                                        }
                                                        if (reviewed > 0) {
                                                            return (
                                                                <Badge variant="outline" className="border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-400 gap-1.5 py-1 px-2.5 font-medium text-xs">
                                                                    <Clock className="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                                                                    <span>Sedang Direviu</span>
                                                                </Badge>
                                                            );
                                                        }
                                                        return (
                                                            <Badge variant="outline" className="border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-400 gap-1.5 py-1 px-2.5 font-medium text-xs">
                                                                <Clock className="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
                                                                <span>Belum Direviu</span>
                                                            </Badge>
                                                        );
                                                    })()}
                                                </td>
                                                <td className="p-4 text-center align-middle whitespace-nowrap">
                                                    {subject.materials_count === 0 ? (
                                                        <span className="text-xs text-muted-foreground">0 Materi</span>
                                                    ) : (
                                                        <Badge
                                                            variant="outline"
                                                            className={
                                                                (subject.reviewed_materials_count ?? 0) === (subject.materials_count ?? 0)
                                                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400 gap-1.5 py-1 px-2.5 text-xs font-normal'
                                                                    : (subject.reviewed_materials_count ?? 0) > 0
                                                                        ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-400 gap-1.5 py-1 px-2.5 text-xs font-normal'
                                                                        : 'border-muted-foreground/30 bg-muted/50 text-muted-foreground gap-1.5 py-1 px-2.5 text-xs font-normal'
                                                            }
                                                            title={`${subject.reviewed_materials_count ?? 0} dari ${subject.materials_count ?? 0} materi telah direviu`}
                                                        >
                                                            {(subject.reviewed_materials_count ?? 0) === (subject.materials_count ?? 0) ? (
                                                                <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                                                            ) : (
                                                                <FileText className="h-3.5 w-3.5" />
                                                            )}
                                                            <span>
                                                                <strong className="font-semibold text-foreground">
                                                                    {subject.reviewed_materials_count ?? 0}
                                                                </strong>
                                                                <span className="opacity-70"> / {subject.materials_count ?? 0}</span>
                                                                <span className="ml-1 text-[11px]">direviu</span>
                                                            </span>
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="p-4 align-middle">
                                                    {subject.reviewers && subject.reviewers.length > 0 ? (
                                                        <div className="flex items-center gap-2">
                                                            <div className="flex -space-x-1.5 overflow-hidden">
                                                                {subject.reviewers.slice(0, 3).map((reviewer) => (
                                                                    <Avatar 
                                                                        key={reviewer.id} 
                                                                        className="inline-block h-6 w-6 rounded-full ring-2 ring-background" 
                                                                        title={`${reviewer.name}${reviewer.unit_kerja ? ` (${reviewer.unit_kerja})` : ''}`}
                                                                    >
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
                                                            <span className="text-xs text-muted-foreground truncate max-w-[150px]">
                                                                {subject.reviewers[0]?.name}
                                                                {subject.reviewers.length > 1 ? ` +${subject.reviewers.length - 1}` : ''}
                                                            </span>
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground">-</span>
                                                    )}
                                                </td>
                                                <td className="p-4 text-right align-middle">
                                                    <Button asChild size="sm" variant="outline" className="gap-1.5">
                                                        <Link href={`/developer/subjects/${subject.id}`}>
                                                            <span>Kelola Materi</span>
                                                            <ArrowRight className="h-3.5 w-3.5" />
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

                    <Pagination
                        links={subjects.links}
                        from={subjects.from}
                        to={subjects.to}
                        total={subjects.total}
                        itemName="mata pelatihan"
                    />
                </Card>
            </div>
        </>
    );
}
