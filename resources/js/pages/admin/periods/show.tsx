import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarRange,
    BookOpen,
    Plus,
    Trash,
    CheckCircle2,
    Clock,
    Search,
    X,
    RotateCcw,
    FileText,
    FileCheck,
    FileSpreadsheet,
    ArrowLeft,
    FolderPlus,
    Users,
    ExternalLink,
    AlertCircle
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import Pagination, { type PaginationLink } from '@/components/pagination';
import { toast } from 'sonner';
import { useEffect, useState } from 'react';
import type { SharedData } from '@/types';

type Training = {
    id: number;
    title: string;
    subjects_count?: number;
    materials_count?: number;
    reviewed_materials_count?: number;
    subjects?: Array<{
        id: number;
        title: string;
        reviewers?: Array<{ id: number; name: string }>;
    }>;
};

type ReviewPeriod = {
    id: number;
    name: string;
    year: number;
    start_date: string | null;
    end_date: string | null;
    status: 'draft' | 'active' | 'closed';
    description: string | null;
    creator?: { id: number; name: string };
    trainings_count: number;
    assignments_count: number;
    submitted_assignments_count: number;
    pending_assignments_count: number;
};

type Props = {
    period: ReviewPeriod;
    trainings: {
        data: Training[];
        current_page: number;
        from: number | null;
        last_page: number;
        links: PaginationLink[];
        per_page: number;
        to: number | null;
        total: number;
    };
    availableTrainings: Array<{
        id: number;
        title: string;
        year?: number | null;
        status: string;
        materials_count?: number;
    }>;
    filters?: {
        search?: string;
        status?: string;
        per_page?: string;
    };
    statuses?: string[];
};

export default function Show({
    period,
    trainings,
    availableTrainings = [],
    filters = {},
    statuses = ['Selesai Reviu', 'Sedang Direviu', 'Belum Direviu'],
}: Props) {
    const { flash } = usePage<{ flash?: { success?: string; error?: string } } & SharedData>().props;
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [perPage, setPerPage] = useState<string>(filters.per_page || '10');

    // Modal Add Training to Period
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [selectedMasterIds, setSelectedMasterIds] = useState<number[]>([]);
    const [masterSearch, setMasterSearch] = useState('');
    const [isSubmittingEnroll, setIsSubmittingEnroll] = useState(false);

    // Modal Confirmation for Removing Training
    const [trainingToRemove, setTrainingToRemove] = useState<Training | null>(null);

    // Bulk selection of trainings in this period
    const [selectedPeriodTrainingIds, setSelectedPeriodTrainingIds] = useState<number[]>([]);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    useEffect(() => {
        setSearchTerm(filters.search || '');
        setStatusFilter(filters.status || '');
        setPerPage(filters.per_page || '10');
    }, [filters.search, filters.status, filters.per_page]);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            `/admin/periods/${period.id}`,
            {
                search: searchTerm || undefined,
                status: statusFilter || undefined,
                per_page: perPage !== '10' ? perPage : undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleReset = () => {
        setSearchTerm('');
        setStatusFilter('');
        setPerPage('10');
        router.get(`/admin/periods/${period.id}`, {}, { preserveState: true, replace: true });
    };

    const handlePerPageChange = (newPerPage: string) => {
        setPerPage(newPerPage);
        router.get(
            `/admin/periods/${period.id}`,
            {
                search: searchTerm || undefined,
                status: statusFilter || undefined,
                per_page: newPerPage !== '10' ? newPerPage : undefined,
            },
            { preserveState: true, replace: true, preserveScroll: true }
        );
    };

    const toggleSelectMaster = (id: number) => {
        setSelectedMasterIds((prev) =>
            prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]
        );
    };

    const filteredAvailableTrainings = availableTrainings.filter((t) =>
        t.title.toLowerCase().includes(masterSearch.toLowerCase())
    );

    const handleAddTrainingsSubmit = () => {
        if (selectedMasterIds.length === 0) return;
        setIsSubmittingEnroll(true);
        router.post(
            `/admin/periods/${period.id}/enroll`,
            { training_ids: selectedMasterIds },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsAddModalOpen(false);
                    setSelectedMasterIds([]);
                    setIsSubmittingEnroll(false);
                },
                onError: () => {
                    setIsSubmittingEnroll(false);
                },
            }
        );
    };

    const handleRemoveTraining = () => {
        if (!trainingToRemove) return;
        router.delete(`/admin/periods/${period.id}/trainings/${trainingToRemove.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setTrainingToRemove(null);
            },
        });
    };

    const isAllSelected =
        trainings.data.length > 0 && trainings.data.every((t) => selectedPeriodTrainingIds.includes(t.id));

    const toggleSelectAll = () => {
        if (isAllSelected) {
            setSelectedPeriodTrainingIds([]);
        } else {
            setSelectedPeriodTrainingIds(trainings.data.map((t) => t.id));
        }
    };

    const toggleSelectTraining = (id: number) => {
        setSelectedPeriodTrainingIds((prev) =>
            prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]
        );
    };

    const handleGenerateBulkStmk = () => {
        if (selectedPeriodTrainingIds.length === 0) {
            toast.error('Pilih minimal satu pelatihan terlebih dahulu.');
            return;
        }
        const params = new URLSearchParams();
        selectedPeriodTrainingIds.forEach((id) => params.append('training_ids[]', id.toString()));
        params.append('type', 'reviu');
        window.location.href = `/admin/trainings/stmk/bulk?${params.toString()}`;
    };

    const hasActiveFilter = Boolean(filters.search || filters.status || (filters.per_page && filters.per_page !== '10'));

    const completionRate =
        period.assignments_count > 0
            ? Math.round((period.submitted_assignments_count / period.assignments_count) * 100)
            : 0;

    return (
        <>
            <Head title={`Pelatihan Reviu - ${period.name}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Back Link & Navigation */}
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" asChild className="gap-1.5 text-xs text-muted-foreground hover:text-foreground">
                        <Link href="/admin/periods">
                            <ArrowLeft className="h-4 w-4" />
                            <span>Kembali ke Daftar Periode</span>
                        </Link>
                    </Button>
                </div>

                {/* Period Banner Card */}
                <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-border pb-5">
                        <div className="space-y-1.5">
                            <div className="flex flex-wrap items-center gap-2.5">
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                    {period.name}
                                </h1>
                                <Badge
                                    variant="outline"
                                    className={
                                        period.status === 'active'
                                            ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400'
                                            : period.status === 'closed'
                                                ? 'border-border bg-muted text-muted-foreground'
                                                : 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-400'
                                    }
                                >
                                    {period.status === 'active' ? 'Periode Aktif' : period.status === 'closed' ? 'Ditutup' : 'Draft'}
                                </Badge>
                                <span className="text-xs font-mono font-medium px-2 py-0.5 rounded bg-muted text-muted-foreground">
                                    Tahun {period.year}
                                </span>
                            </div>
                            <p className="text-sm text-muted-foreground max-w-3xl">
                                {period.description || 'Tidak ada catatan arahan khusus untuk periode ini.'}
                            </p>
                            <div className="flex items-center gap-4 text-xs text-muted-foreground pt-1">
                                <div className="flex items-center gap-1.5">
                                    <Clock className="h-3.5 w-3.5" />
                                    <span>
                                        Jadwal: {period.start_date ? new Date(period.start_date).toLocaleDateString('id-ID') : '-'} s.d.{' '}
                                        {period.end_date ? new Date(period.end_date).toLocaleDateString('id-ID') : '-'}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Top Action Buttons */}
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                asChild
                                className="h-9 gap-1.5 text-xs text-emerald-700 border-emerald-300 hover:bg-emerald-50 dark:text-emerald-400 dark:border-emerald-800 dark:hover:bg-emerald-950/30"
                            >
                                <a href={`/admin/reports/comments?period_id=${period.id}`} title="Ekspor rekapitulasi komentar dan catatan pada periode ini">
                                    <FileSpreadsheet className="h-4 w-4" />
                                    <span>Ekspor Laporan Reviu (CSV)</span>
                                </a>
                            </Button>
                            <Button
                                size="sm"
                                onClick={() => {
                                    setSelectedMasterIds([]);
                                    setMasterSearch('');
                                    setIsAddModalOpen(true);
                                }}
                                className="h-9 gap-1.5 text-xs shadow-sm"
                            >
                                <Plus className="h-4 w-4" />
                                <span>+ Daftarkan Pelatihan</span>
                            </Button>
                        </div>
                    </div>

                    {/* Stats Metrics Grid */}
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 pt-5">
                        <div className="flex flex-col gap-1 p-3.5 rounded-lg bg-muted/40 border border-border/60">
                            <span className="text-xs text-muted-foreground font-medium">Pelatihan Terdaftar</span>
                            <div className="flex items-baseline gap-2">
                                <span className="text-2xl font-bold text-foreground">{period.trainings_count}</span>
                                <span className="text-xs text-muted-foreground">paket</span>
                            </div>
                        </div>

                        <div className="flex flex-col gap-1 p-3.5 rounded-lg bg-muted/40 border border-border/60">
                            <span className="text-xs text-muted-foreground font-medium">Tugas Reviu Materi</span>
                            <div className="flex items-baseline gap-2">
                                <span className="text-2xl font-bold text-foreground">{period.assignments_count}</span>
                                <span className="text-xs text-muted-foreground">penugasan</span>
                            </div>
                        </div>

                        <div className="flex flex-col gap-1 p-3.5 rounded-lg bg-muted/40 border border-border/60">
                            <span className="text-xs text-muted-foreground font-medium">Tuntas Direviu</span>
                            <div className="flex items-baseline gap-2">
                                <span className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                                    {period.submitted_assignments_count}
                                </span>
                                <span className="text-xs text-muted-foreground">/ {period.assignments_count} ({completionRate}%)</span>
                            </div>
                        </div>

                        <div className="flex flex-col gap-1 p-3.5 rounded-lg bg-muted/40 border border-border/60">
                            <span className="text-xs text-muted-foreground font-medium">Menunggu / Sedang Proses</span>
                            <div className="flex items-baseline gap-2">
                                <span className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                    {period.pending_assignments_count}
                                </span>
                                <span className="text-xs text-muted-foreground">materi lagi</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Bulk Action Bar when trainings in this period are selected */}
                {selectedPeriodTrainingIds.length > 0 && (
                    <div className="flex items-center justify-between p-3 px-4 bg-primary/10 border border-primary/20 rounded-lg text-sm text-foreground">
                        <div className="flex items-center gap-2">
                            <CheckCircle2 className="h-4 w-4 text-primary" />
                            <span className="font-medium">{selectedPeriodTrainingIds.length} pelatihan terpilih</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={handleGenerateBulkStmk}
                                className="h-8 gap-1.5 text-xs bg-background"
                            >
                                <FileCheck className="h-3.5 w-3.5 text-emerald-600" />
                                <span>Cetak STMK Reviu Massal</span>
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => setSelectedPeriodTrainingIds([])}
                                className="h-8 text-xs text-muted-foreground"
                            >
                                Batal
                            </Button>
                        </div>
                    </div>
                )}

                {/* Filter & Search Bar */}
                <Card className="p-4">
                    <form onSubmit={handleSearch} className="flex flex-wrap items-center gap-3">
                        <div className="relative flex-1 min-w-[220px]">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder="Cari nama pelatihan dalam periode ini..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="pl-9 pr-8 h-9 text-xs"
                            />
                            {searchTerm && (
                                <button
                                    type="button"
                                    onClick={() => setSearchTerm('')}
                                    className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                >
                                    <X className="h-3.5 w-3.5" />
                                </button>
                            )}
                        </div>

                        <div className="w-44">
                            <select
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                                className="w-full h-9 rounded-md border border-input bg-background px-3 text-xs text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                            >
                                <option value="">Semua Status Reviu</option>
                                {statuses.map((s) => (
                                    <option key={s} value={s}>
                                        {s}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <Button type="submit" size="sm" className="h-9 gap-1.5 text-xs">
                            <Search className="h-3.5 w-3.5" />
                            <span>Cari</span>
                        </Button>

                        {hasActiveFilter && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={handleReset}
                                className="h-9 text-xs text-muted-foreground hover:text-foreground gap-1.5"
                            >
                                <RotateCcw className="h-3.5 w-3.5" />
                                <span>Reset</span>
                            </Button>
                        )}
                    </form>
                </Card>

                {/* Table of Enrolled Trainings */}
                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground border-b border-border">
                                <tr>
                                    <th className="w-10 p-4">
                                        <Checkbox
                                            checked={isAllSelected}
                                            onCheckedChange={toggleSelectAll}
                                            aria-label="Pilih semua pelatihan"
                                        />
                                    </th>
                                    <th className="font-medium p-4">Pelatihan &amp; Kurikulum</th>
                                    <th className="font-medium p-4 w-52">Reviewer Ditugaskan</th>
                                    <th className="font-medium p-4 w-36 text-center">Status Reviu</th>
                                    <th className="font-medium p-4 w-40 text-center">Materi Direviu</th>
                                    <th className="font-medium p-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {trainings.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="p-12 text-center text-muted-foreground">
                                            <div className="flex flex-col items-center justify-center space-y-3">
                                                <div className="p-3 rounded-full bg-muted">
                                                    <BookOpen className="h-6 w-6 text-muted-foreground" />
                                                </div>
                                                <div className="text-base font-medium text-foreground">
                                                    {hasActiveFilter
                                                        ? 'Tidak ada pelatihan yang cocok dengan filter pencarian.'
                                                        : 'Belum Ada Pelatihan yang Didaftarkan ke Periode Ini'}
                                                </div>
                                                <p className="text-xs text-muted-foreground max-w-sm">
                                                    {hasActiveFilter
                                                        ? 'Coba atur ulang kata kunci atau filter status Anda.'
                                                        : 'Klik tombol "+ Daftarkan Pelatihan" di atas untuk memilih pelatihan dari Master Data yang akan dievaluasi pada periode ini.'}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    trainings.data.map((training) => {
                                        const total = training.materials_count ?? 0;
                                        const reviewed = training.reviewed_materials_count ?? 0;

                                        // Extract unique reviewers across all subjects in this training
                                        const reviewerNames: string[] = [];
                                        training.subjects?.forEach((s) => {
                                            s.reviewers?.forEach((r) => {
                                                if (!reviewerNames.includes(r.name)) {
                                                    reviewerNames.push(r.name);
                                                }
                                            });
                                        });

                                        return (
                                            <tr key={training.id} className="hover:bg-muted/50 transition-colors">
                                                <td className="p-4 align-middle">
                                                    <Checkbox
                                                        checked={selectedPeriodTrainingIds.includes(training.id)}
                                                        onCheckedChange={() => toggleSelectTraining(training.id)}
                                                        aria-label={`Pilih ${training.title}`}
                                                    />
                                                </td>
                                                <td className="p-4">
                                                    <div className="flex flex-col gap-1">
                                                        <Link
                                                            href={`/admin/trainings/${training.id}`}
                                                            className="font-semibold text-primary hover:underline text-sm"
                                                        >
                                                            {training.title}
                                                        </Link>
                                                        <span className="text-xs text-muted-foreground">
                                                            {training.subjects_count ?? 0} Mata Pelatihan • {training.materials_count ?? 0} Materi
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="p-4 align-middle text-xs">
                                                    {reviewerNames.length > 0 ? (
                                                        <div className="flex flex-wrap gap-1">
                                                            {reviewerNames.map((name, idx) => (
                                                                <Badge
                                                                    key={idx}
                                                                    variant="outline"
                                                                    className="border-muted bg-muted/60 text-foreground text-[11px] font-normal"
                                                                >
                                                                    {name}
                                                                </Badge>
                                                            ))}
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground italic text-xs">
                                                            Belum ada reviewer
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="p-4 text-center align-middle">
                                                    {(() => {
                                                        if (total === 0) {
                                                            return (
                                                                <Badge variant="outline" className="border-muted bg-muted/50 text-muted-foreground gap-1.5 py-1 px-2.5 text-xs font-normal">
                                                                    <Clock className="h-3.5 w-3.5" />
                                                                    <span>Belum Ada Materi</span>
                                                                </Badge>
                                                            );
                                                        }
                                                        if (reviewed >= total) {
                                                            return (
                                                                <Badge variant="outline" className="border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400 gap-1.5 py-1 px-2.5 text-xs font-medium">
                                                                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                                                                    <span>Selesai Reviu</span>
                                                                </Badge>
                                                            );
                                                        }
                                                        if (reviewed > 0) {
                                                            return (
                                                                <Badge variant="outline" className="border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-400 gap-1.5 py-1 px-2.5 text-xs font-medium">
                                                                    <Clock className="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                                                                    <span>Sedang Direviu</span>
                                                                </Badge>
                                                            );
                                                        }
                                                        return (
                                                            <Badge variant="outline" className="border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-400 gap-1.5 py-1 px-2.5 text-xs font-medium">
                                                                <Clock className="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
                                                                <span>Belum Direviu</span>
                                                            </Badge>
                                                        );
                                                    })()}
                                                </td>
                                                <td className="p-4 text-center align-middle whitespace-nowrap">
                                                    {total === 0 ? (
                                                        <span className="text-xs text-muted-foreground italic">0 materi</span>
                                                    ) : (
                                                        <div className="inline-flex flex-col items-center gap-1">
                                                            <Badge
                                                                variant="outline"
                                                                className={
                                                                    reviewed === total
                                                                        ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400 gap-1.5 py-0.5 px-2 text-xs font-normal'
                                                                        : reviewed > 0
                                                                            ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-400 gap-1.5 py-0.5 px-2 text-xs font-normal'
                                                                            : 'border-muted-foreground/30 bg-muted/50 text-muted-foreground gap-1.5 py-0.5 px-2 text-xs font-normal'
                                                                }
                                                            >
                                                                {reviewed === total ? (
                                                                    <CheckCircle2 className="h-3 w-3 text-emerald-600 dark:text-emerald-400" />
                                                                ) : (
                                                                    <FileCheck className="h-3 w-3" />
                                                                )}
                                                                <span>
                                                                    <strong className="font-semibold text-foreground">{reviewed}</strong>
                                                                    <span className="opacity-70"> / {total}</span>
                                                                </span>
                                                            </Badge>
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="p-4 text-right align-middle">
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            asChild
                                                            className="h-8 border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900/50 dark:text-emerald-400 dark:hover:bg-emerald-950/30 text-xs"
                                                        >
                                                            <a
                                                                href={`/admin/trainings/${training.id}/stmk/reviu`}
                                                                title="Unduh Berkas STMK Reviu"
                                                            >
                                                                <FileCheck className="mr-1 h-3.5 w-3.5" />
                                                                STMK Reviu
                                                            </a>
                                                        </Button>

                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            asChild
                                                            className="h-8 border-violet-200 text-violet-700 hover:bg-violet-50 dark:border-violet-900/50 dark:text-violet-400 dark:hover:bg-violet-950/30 text-xs"
                                                        >
                                                            <a
                                                                href={`/admin/reports/comments?period_id=${period.id}&training_id=${training.id}`}
                                                                title="Ekspor Komentar Pelatihan Ini di Periode Ini"
                                                            >
                                                                <FileSpreadsheet className="mr-1 h-3.5 w-3.5" />
                                                                Laporan
                                                            </a>
                                                        </Button>

                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => setTrainingToRemove(training)}
                                                            className="h-8 text-destructive hover:text-destructive hover:bg-destructive/10 border-destructive/30 text-xs"
                                                            title="Keluarkan dari Periode Ini"
                                                        >
                                                            <Trash className="h-3.5 w-3.5" />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {trainings.total > 0 && (
                        <div className="p-4 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-4 bg-card/50">
                            <div className="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                                <span>
                                    Menampilkan{' '}
                                    <span className="font-semibold text-foreground">{trainings.from ?? 0}</span> s.d.{' '}
                                    <span className="font-semibold text-foreground">{trainings.to ?? 0}</span> dari{' '}
                                    <span className="font-semibold text-foreground">{trainings.total}</span> pelatihan terdaftar
                                </span>

                                <div className="flex items-center gap-1.5 pl-3 border-l border-border">
                                    <span>Tampilkan:</span>
                                    <select
                                        value={perPage}
                                        onChange={(e) => handlePerPageChange(e.target.value)}
                                        className="h-8 rounded-md border border-input bg-background px-2 text-xs text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-ring cursor-pointer"
                                    >
                                        <option value="10">10 data</option>
                                        <option value="25">25 data</option>
                                        <option value="50">50 data</option>
                                        <option value="100">100 data</option>
                                        <option value="all">Tampilkan semua data</option>
                                    </select>
                                </div>
                            </div>

                            {/* Pagination buttons */}
                            {trainings.links && trainings.links.length > 3 && (
                                <div className="flex items-center gap-1">
                                    {trainings.links.map((link, idx) => {
                                        const isPrev = link.label.includes('Previous');
                                        const isNext = link.label.includes('Next');

                                        let label = link.label;
                                        if (isPrev) label = 'Sebelumnya';
                                        if (isNext) label = 'Berikutnya';

                                        if (!link.url) {
                                            return (
                                                <Button
                                                    key={idx}
                                                    variant="outline"
                                                    size="sm"
                                                    disabled
                                                    className="h-8 px-2 sm:px-3 text-xs opacity-50"
                                                >
                                                    {label}
                                                </Button>
                                            );
                                        }

                                        return (
                                            <Button
                                                key={idx}
                                                variant={link.active ? 'default' : 'outline'}
                                                size="sm"
                                                asChild
                                                className={`h-8 px-2 sm:px-3 text-xs ${
                                                    link.active ? 'font-semibold' : ''
                                                }`}
                                            >
                                                <Link
                                                    href={link.url}
                                                    preserveScroll
                                                    preserveState
                                                >
                                                    <span dangerouslySetInnerHTML={{ __html: label }} />
                                                </Link>
                                            </Button>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    )}
                </Card>
            </div>

            {/* Modal: Tambahkan Pelatihan dari Master Data */}
            <Dialog open={isAddModalOpen} onOpenChange={setIsAddModalOpen}>
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <FolderPlus className="h-5 w-5 text-primary" />
                            <span>Daftarkan Pelatihan ke Periode</span>
                        </DialogTitle>
                        <DialogDescription>
                            Pilih pelatihan dari Master Data yang akan diikutsertakan dalam siklus reviu untuk <strong>{period.name}</strong>.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-2">
                        {/* Search in master list */}
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder="Cari nama pelatihan dari master data..."
                                value={masterSearch}
                                onChange={(e) => setMasterSearch(e.target.value)}
                                className="pl-9 text-xs"
                            />
                        </div>

                        {/* List of Available Trainings */}
                        <div className="max-h-72 overflow-y-auto rounded-lg border border-border divide-y divide-border">
                            {filteredAvailableTrainings.length === 0 ? (
                                <div className="p-6 text-center text-xs text-muted-foreground">
                                    {masterSearch
                                        ? 'Tidak ditemukan pelatihan yang sesuai pencarian.'
                                        : 'Semua pelatihan di Master Data sudah terdaftar dalam periode ini.'}
                                </div>
                            ) : (
                                filteredAvailableTrainings.map((t) => {
                                    const isSelected = selectedMasterIds.includes(t.id);
                                    return (
                                        <div
                                            key={t.id}
                                            onClick={() => toggleSelectMaster(t.id)}
                                            className={`p-3 flex items-center justify-between cursor-pointer transition-colors ${
                                                isSelected ? 'bg-primary/5' : 'hover:bg-muted/50'
                                            }`}
                                        >
                                            <div className="flex items-center gap-3">
                                                <Checkbox
                                                    checked={isSelected}
                                                    onCheckedChange={() => toggleSelectMaster(t.id)}
                                                    onClick={(e) => e.stopPropagation()}
                                                />
                                                <div>
                                                    <p className="text-xs font-semibold text-foreground">
                                                        {t.title}
                                                    </p>
                                                    <span className="text-[11px] text-muted-foreground">
                                                        {t.materials_count ?? 0} materi • Tahun {t.year || '-'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>

                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span>{selectedMasterIds.length} pelatihan dipilih</span>
                            {filteredAvailableTrainings.length > 0 && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (selectedMasterIds.length === filteredAvailableTrainings.length) {
                                            setSelectedMasterIds([]);
                                        } else {
                                            setSelectedMasterIds(filteredAvailableTrainings.map((t) => t.id));
                                        }
                                    }}
                                    className="text-primary hover:underline"
                                >
                                    {selectedMasterIds.length === filteredAvailableTrainings.length
                                        ? 'Batal pilih semua'
                                        : 'Pilih semua yang tampil'}
                                </button>
                            )}
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setIsAddModalOpen(false)}
                            disabled={isSubmittingEnroll}
                        >
                            Batal
                        </Button>
                        <Button
                            onClick={handleAddTrainingsSubmit}
                            disabled={selectedMasterIds.length === 0 || isSubmittingEnroll}
                            className="gap-1.5"
                        >
                            <FolderPlus className="h-4 w-4" />
                            <span>{isSubmittingEnroll ? 'Menyimpan...' : `Daftarkan (${selectedMasterIds.length})`}</span>
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Modal Konfirmasi Keluarkan Pelatihan */}
            <Dialog open={!!trainingToRemove} onOpenChange={(open) => !open && setTrainingToRemove(null)}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-destructive">
                            <AlertCircle className="h-5 w-5" />
                            <span>Keluarkan Pelatihan dari Periode</span>
                        </DialogTitle>
                        <DialogDescription>
                            Apakah Anda yakin ingin mengeluarkan pelatihan <strong>"{trainingToRemove?.title}"</strong> dari periode <strong>{period.name}</strong>?
                        </DialogDescription>
                    </DialogHeader>

                    <div className="py-2 text-xs text-muted-foreground space-y-2">
                        <p>
                            Pelatihan ini tetap tersimpan aman di <strong>Master Data Pelatihan</strong>.
                        </p>
                        <p className="text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 p-2.5 rounded border border-amber-200 dark:border-amber-800">
                            Catatan penugasan reviu draf untuk periode ini akan dilepas. Ulasan yang telah tuntas tidak akan terhapus dari riwayat.
                        </p>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setTrainingToRemove(null)}>
                            Batal
                        </Button>
                        <Button variant="destructive" onClick={handleRemoveTraining}>
                            Ya, Keluarkan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
