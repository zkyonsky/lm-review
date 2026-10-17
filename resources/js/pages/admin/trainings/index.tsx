import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { 
    BookOpen, 
    Plus, 
    Edit, 
    Trash, 
    FileText, 
    FileCheck, 
    FileSpreadsheet,
    Search, 
    X, 
    CheckCircle2, 
    Clock,
    RotateCcw,
    Upload,
    Download,
    CalendarRange,
    FolderPlus
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
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
    status: string;
    year?: number | null;
    created_at: string;
    subjects_count?: number;
    materials_count?: number;
    reviewed_materials_count?: number;
    periods?: Array<{ id: number; name: string; year: number; status: string }>;
};

type Props = {
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
    filters?: {
        search?: string;
        status?: string;
        period_id?: string;
    };
    statuses?: string[];
    periods?: Array<{ id: number; name: string; year: number; status: string }>;
};

export default function Index({ 
    trainings, 
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
    const [isDeletingBulk, setIsDeletingBulk] = useState(false);
    const [isConfirmBulkDeleteOpen, setIsConfirmBulkDeleteOpen] = useState(false);
    const [isImportModalOpen, setIsImportModalOpen] = useState(false);
    const [isEnrollModalOpen, setIsEnrollModalOpen] = useState(false);
    const [selectedPeriodId, setSelectedPeriodId] = useState<string>('');
    const [isEnrolling, setIsEnrolling] = useState(false);

    const { 
        data: importData, 
        setData: setImportData, 
        post: postImport, 
        processing: isImporting, 
        errors: importErrors, 
        reset: resetImport 
    } = useForm<{ file: File | null }>({
        file: null,
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
        if (flash?.warning) {
            toast.warning(flash.warning, { duration: 8000 });
        }
    }, [flash]);

    const handleImportSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!importData.file) {
            toast.error('Silakan pilih file Excel terlebih dahulu.');
            return;
        }

        postImport('/admin/trainings/import', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setIsImportModalOpen(false);
                resetImport();
            },
        });
    };

    // Sinkronisasi filter saat props berubah (misal tombol reset atau navigasi balik)
    useEffect(() => {
        setSearchTerm(filters.search || '');
        setStatusFilter(filters.status || '');
    }, [filters.search, filters.status]);

    const isAllSelected = trainings.data.length > 0 && trainings.data.every((t) => selectedIds.includes(t.id));

    const toggleSelectAll = () => {
        if (isAllSelected) {
            setSelectedIds([]);
        } else {
            setSelectedIds(trainings.data.map((t) => t.id));
        }
    };

    const toggleSelect = (id: number) => {
        setSelectedIds((prev) =>
            prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]
        );
    };

    const applyFilter = (newSearch: string, newStatus: string, newPeriod: string = periodFilter) => {
        router.get(
            '/admin/trainings',
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
        router.get('/admin/trainings', {}, { preserveState: true, replace: true });
    };

    const handleBulkDownload = (type: 'pengembangan' | 'reviu') => {
        if (selectedIds.length === 0) {
            toast.error('Pilih setidaknya satu pelatihan terlebih dahulu.');
            return;
        }

        const params = new URLSearchParams();
        params.append('type', type);
        selectedIds.forEach((id) => params.append('training_ids[]', id.toString()));

        window.location.href = `/admin/trainings/stmk/bulk?${params.toString()}`;
    };

    const handleBulkStatusChange = (newStatus: string) => {
        if (selectedIds.length === 0) {
            toast.error('Pilih setidaknya satu pelatihan terlebih dahulu.');
            return;
        }

        setIsUpdatingStatus(true);
        router.post(
            '/admin/trainings/status/bulk',
            {
                training_ids: selectedIds,
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

    const handleBulkDelete = () => {
        if (selectedIds.length === 0) {
            toast.error('Pilih setidaknya satu pelatihan terlebih dahulu.');
            return;
        }

        setIsDeletingBulk(true);
        router.post(
            '/admin/trainings/delete/bulk',
            {
                training_ids: selectedIds,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedIds([]);
                    setIsConfirmBulkDeleteOpen(false);
                    setIsDeletingBulk(false);
                },
                onError: () => {
                    setIsDeletingBulk(false);
                },
            }
        );
    };

    const handleEnrollSubmit = () => {
        if (!selectedPeriodId || selectedIds.length === 0) return;
        setIsEnrolling(true);
        router.post(
            `/admin/periods/${selectedPeriodId}/enroll`,
            { training_ids: selectedIds },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsEnrollModalOpen(false);
                    setSelectedIds([]);
                    setIsEnrolling(false);
                },
                onError: () => {
                    setIsEnrolling(false);
                },
            }
        );
    };

    const hasActiveFilter = Boolean(filters.search || filters.status || filters.period_id);

    return (
        <>
            <Head title="Master Pelatihan" />
            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Master Data Pelatihan</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Bank data kurikulum pelatihan, silabus mata pelatihan, dan materi pembelajaran.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button 
                            type="button" 
                            variant="outline" 
                            onClick={() => setIsImportModalOpen(true)}
                            className="gap-2"
                        >
                            <Upload className="h-4 w-4" />
                            <span>Upload Massal (Excel)</span>
                        </Button>
                        <Button asChild>
                            <Link href="/admin/trainings/create">
                                <Plus className="mr-2 h-4 w-4" />
                                Tambah Pelatihan
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="flex flex-col md:flex-row items-stretch md:items-center gap-3">
                    <form onSubmit={handleSearchSubmit} className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
                        <Input
                            placeholder="Cari nama pelatihan..."
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
                            aria-label="Filter status pelatihan"
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

                {/* Bulk Action Toolbar */}
                {selectedIds.length > 0 && (
                    <div className="flex flex-wrap items-center justify-between gap-3 bg-muted/70 border border-border p-3.5 rounded-lg animate-in fade-in duration-200">
                        <div className="flex items-center gap-2">
                            <Badge variant="secondary" className="font-medium text-xs px-2.5 py-0.5">
                                {selectedIds.length} Pelatihan Terpilih
                            </Badge>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {/* Daftarkan ke Periode Reviu Massal */}
                            {periods && periods.length > 0 && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => {
                                        const firstActive = periods.find(p => p.status === 'active') || periods[0];
                                        setSelectedPeriodId(String(firstActive?.id || ''));
                                        setIsEnrollModalOpen(true);
                                    }}
                                    className="h-8 border-primary/40 text-primary hover:bg-primary/5 gap-1.5"
                                >
                                    <FolderPlus className="h-3.5 w-3.5" />
                                    <span>Daftarkan ke Periode</span>
                                </Button>
                            )}

                            {/* Separator */}
                            <div className="h-4 w-px bg-border mx-1 hidden sm:block" />

                            {/* STMK Massal */}
                            <span className="text-xs text-muted-foreground font-medium hidden md:inline">
                                STMK:
                            </span>
                            <Button
                                size="sm"
                                disabled={isDeletingBulk}
                                onClick={() => handleBulkDownload('pengembangan')}
                                className="h-8 bg-blue-600 hover:bg-blue-700 text-white gap-1.5"
                            >
                                <FileText className="h-3.5 w-3.5" />
                                STMK Pengembang
                            </Button>

                            {/* Separator */}
                            <div className="h-4 w-px bg-border mx-1 hidden sm:block" />

                            {/* Hapus Terpilih */}
                            <Button
                                size="sm"
                                variant="destructive"
                                disabled={isDeletingBulk || isUpdatingStatus}
                                onClick={() => setIsConfirmBulkDeleteOpen(true)}
                                className="h-8 gap-1.5"
                            >
                                <Trash className="h-3.5 w-3.5" />
                                <span>Hapus Terpilih</span>
                            </Button>

                            <Button
                                variant="ghost"
                                size="sm"
                                disabled={isDeletingBulk}
                                onClick={() => setSelectedIds([])}
                                className="h-8 text-xs text-muted-foreground hover:text-foreground"
                            >
                                Batal
                            </Button>
                        </div>
                    </div>
                )}

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
                                            aria-label="Pilih semua pelatihan"
                                        />
                                    </th>
                                    <th className="font-medium p-4">Judul Pelatihan &amp; Kurikulum</th>
                                    <th className="font-medium p-4 w-28 text-center">Tahun</th>
                                    <th className="font-medium p-4 w-48">Status di Periode Reviu</th>
                                    <th className="font-medium p-4 w-32 text-center">Status</th>
                                    <th className="font-medium p-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {trainings.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="p-8 text-center text-muted-foreground">
                                            {hasActiveFilter
                                                ? 'Tidak ada pelatihan yang cocok dengan filter pencarian.'
                                                : 'Belum ada data pelatihan master.'}
                                        </td>
                                    </tr>
                                ) : (
                                    trainings.data.map((training) => {
                                        return (
                                            <tr key={training.id} className="hover:bg-muted/50 transition-colors">
                                                <td className="p-4 align-middle">
                                                    <Checkbox
                                                        checked={selectedIds.includes(training.id)}
                                                        onCheckedChange={() => toggleSelect(training.id)}
                                                        aria-label={`Pilih ${training.title}`}
                                                    />
                                                </td>
                                                <td className="p-4">
                                                    <div className="flex flex-col gap-0.5">
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
                                                <td className="p-4 text-center align-middle font-mono font-medium text-xs">
                                                    {training.year ?? '-'}
                                                </td>
                                                <td className="p-4 align-middle">
                                                    {training.periods && training.periods.length > 0 ? (
                                                        <div className="flex flex-wrap gap-1">
                                                            {training.periods.map((p) => (
                                                                <Link key={p.id} href={`/admin/periods/${p.id}`}>
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400 gap-1 text-[11px] hover:underline"
                                                                        title="Klik untuk membuka detail periode ini"
                                                                    >
                                                                        <CalendarRange className="h-3 w-3" />
                                                                        <span>{p.name}</span>
                                                                    </Badge>
                                                                </Link>
                                                            ))}
                                                        </div>
                                                    ) : (
                                                        <Badge variant="outline" className="border-border bg-muted/40 text-muted-foreground text-[11px] font-normal">
                                                            Belum Terdaftar
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="p-4 text-center align-middle">
                                                    <Badge variant="outline" className="font-normal text-xs">
                                                        {training.status}
                                                    </Badge>
                                                </td>
                                                <td className="p-4 text-right">
                                                    <div className="flex flex-wrap items-center justify-end gap-1.5">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => {
                                                                setSelectedIds([training.id]);
                                                                setSelectedPeriodId('');
                                                                setIsEnrollModalOpen(true);
                                                            }}
                                                            className="h-8 gap-1.5 text-xs text-primary border-primary/30 hover:bg-primary/5"
                                                            title="Daftarkan ke Periode Reviu"
                                                        >
                                                            <FolderPlus className="h-3.5 w-3.5" />
                                                            <span>Daftarkan</span>
                                                        </Button>

                                                        <Button variant="outline" size="sm" asChild className="h-8 text-xs">
                                                            <Link href={`/admin/trainings/${training.id}`}>
                                                                <BookOpen className="mr-1.5 h-3.5 w-3.5" />
                                                                Silabus &amp; Materi
                                                            </Link>
                                                        </Button>

                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            asChild
                                                            className="h-8 border-blue-200 text-blue-700 hover:bg-blue-50 dark:border-blue-900/50 dark:text-blue-400 dark:hover:bg-blue-950/30 text-xs"
                                                        >
                                                            <a
                                                                href={`/admin/trainings/${training.id}/stmk/pengembangan`}
                                                                title="Generate STMK Pengembang"
                                                            >
                                                                <FileText className="mr-1.5 h-3.5 w-3.5" />
                                                                STMK
                                                            </a>
                                                        </Button>

                                                        <Button variant="outline" size="sm" asChild className="h-8 w-8 p-0" title="Edit Pelatihan">
                                                            <Link href={`/admin/trainings/${training.id}/edit`}>
                                                                <Edit className="h-3.5 w-3.5" />
                                                            </Link>
                                                        </Button>

                                                        <Button 
                                                            variant="outline" 
                                                            size="sm" 
                                                            asChild 
                                                            className="h-8 w-8 p-0 text-destructive hover:text-destructive hover:bg-destructive/10 border-destructive/30"
                                                            title="Hapus Pelatihan"
                                                        >
                                                            <Link 
                                                                href={`/admin/trainings/${training.id}`}
                                                                method="delete"
                                                                as="button"
                                                                onClick={(e) => {
                                                                    if (!confirm('Apakah Anda yakin ingin menghapus pelatihan ini?')) {
                                                                        e.preventDefault();
                                                                    }
                                                                }}
                                                            >
                                                                <Trash className="h-3.5 w-3.5" />
                                                            </Link>
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

                    {/* Pagination */}
                    <Pagination
                        links={trainings.links}
                        from={trainings.from}
                        to={trainings.to}
                        total={trainings.total}
                        itemName="pelatihan"
                    />
                </Card>
            </div>

            {/* Modal Upload Massal (Excel) */}
            <Dialog 
                open={isImportModalOpen} 
                onOpenChange={(open) => {
                    setIsImportModalOpen(open);
                    if (!open) resetImport();
                }}
            >
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-xl">
                            <FileSpreadsheet className="h-5 w-5 text-primary" />
                            Upload Massal Data Pelatihan
                        </DialogTitle>
                        <DialogDescription>
                            Unggah satu file Excel (.xlsx, .xls) atau CSV (.csv) untuk membuat data pelatihan, mata pelatihan, link materi, serta penugasan pengembang dan reviewer.
                        </DialogDescription>
                    </DialogHeader>

                    {/* Step 1: Download Template */}
                    <div className="rounded-lg border bg-muted/40 p-4 space-y-2.5">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <h4 className="text-sm font-semibold text-foreground">1. Unduh Template Excel</h4>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Gunakan format template resmi agar data dapat dipetakan secara tepat.
                                </p>
                            </div>
                            <Button variant="secondary" size="sm" asChild className="gap-1.5 shrink-0 self-start sm:self-auto">
                                <a href="/admin/trainings/import/template" download>
                                    <Download className="h-3.5 w-3.5" />
                                    <span>Unduh Template</span>
                                </a>
                            </Button>
                        </div>
                        <div className="text-xs text-muted-foreground pt-2 border-t border-border/60">
                            <span className="font-medium text-foreground">Kolom yang didukung: </span>
                            Nama Pelatihan, Nama Mata Pelatihan, Tipe Materi (video/pdf/scorm), Link Materi (Google Drive, dapat mengisi lebih dari 1 tautan dalam 1 sel dengan tombol Enter / Alt+Enter atau koma), Pengembang (Dropdown/Nama), Reviewer (Dropdown/Nama).
                        </div>
                    </div>

                    {/* Step 2: Upload Form */}
                    <form onSubmit={handleImportSubmit} className="space-y-4 pt-1">
                        <div className="space-y-1.5">
                            <label className="text-sm font-semibold text-foreground">
                                2. Pilih File Excel / CSV
                            </label>
                            <div className="relative border-2 border-dashed border-border hover:border-primary/50 transition-colors rounded-lg p-6 text-center bg-card flex flex-col items-center justify-center gap-2">
                                <input
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    onChange={(e) => {
                                        const file = e.target.files?.[0] || null;
                                        setImportData('file', file);
                                    }}
                                    className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                />
                                {importData.file ? (
                                    <div className="flex flex-col items-center gap-1.5 pointer-events-none">
                                        <div className="p-2.5 rounded-full bg-primary/10 text-primary">
                                            <FileSpreadsheet className="h-6 w-6" />
                                        </div>
                                        <p className="text-sm font-medium text-foreground">{importData.file.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {(importData.file.size / 1024).toFixed(1)} KB
                                        </p>
                                    </div>
                                ) : (
                                    <div className="flex flex-col items-center gap-1 pointer-events-none">
                                        <div className="p-2.5 rounded-full bg-muted text-muted-foreground">
                                            <Upload className="h-6 w-6" />
                                        </div>
                                        <p className="text-sm font-medium text-foreground">
                                            Klik untuk memilih file atau seret file ke sini
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Format file: .xlsx, .xls, .csv (Maksimal 10MB)
                                        </p>
                                    </div>
                                )}
                            </div>
                            {importErrors.file && (
                                <p className="text-xs text-destructive font-medium mt-1">{importErrors.file}</p>
                            )}
                        </div>

                        <DialogFooter className="gap-2 sm:gap-0 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setIsImportModalOpen(false);
                                    resetImport();
                                }}
                                disabled={isImporting}
                            >
                                Batal
                            </Button>
                            <Button 
                                type="submit" 
                                disabled={isImporting || !importData.file} 
                                className="gap-2"
                            >
                                {isImporting ? (
                                    <>
                                        <Clock className="h-4 w-4 animate-spin" />
                                        <span>Mengimpor Data...</span>
                                    </>
                                ) : (
                                    <>
                                        <Upload className="h-4 w-4" />
                                        <span>Unggah & Impor</span>
                                    </>
                                )}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal Konfirmasi Hapus Massal */}
            <Dialog 
                open={isConfirmBulkDeleteOpen} 
                onOpenChange={(open) => {
                    if (!isDeletingBulk) {
                        setIsConfirmBulkDeleteOpen(open);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-destructive">
                            <Trash className="h-5 w-5" />
                            Hapus {selectedIds.length} Pelatihan Terpilih?
                        </DialogTitle>
                        <DialogDescription className="pt-2 text-sm leading-relaxed text-muted-foreground">
                            Apakah Anda yakin ingin menghapus <strong>{selectedIds.length} pelatihan</strong> yang telah dicentang?
                            Pelatihan yang dihapus beserta mata pelatihannya akan dipindahkan ke arsip/tempat sampah (soft delete).
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter className="gap-2 sm:gap-0 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsConfirmBulkDeleteOpen(false)}
                            disabled={isDeletingBulk}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={handleBulkDelete}
                            disabled={isDeletingBulk}
                            className="gap-2"
                        >
                            {isDeletingBulk ? (
                                <>
                                    <Clock className="h-4 w-4 animate-spin" />
                                    <span>Menghapus...</span>
                                </>
                            ) : (
                                <>
                                    <Trash className="h-4 w-4" />
                                    <span>Ya, Hapus {selectedIds.length} Pelatihan</span>
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            {/* Modal Daftarkan ke Periode Reviu Massal */}
            <Dialog open={isEnrollModalOpen} onOpenChange={setIsEnrollModalOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <FolderPlus className="h-5 w-5 text-primary" />
                            <span>Daftarkan ke Periode Reviu</span>
                        </DialogTitle>
                        <DialogDescription>
                            Daftarkan <strong className="text-foreground">{selectedIds.length} pelatihan</strong> terpilih ke dalam siklus periode reviu berikut:
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-2">
                        <div className="space-y-2">
                            <label className="text-xs font-semibold text-foreground">
                                Pilih Periode Reviu Tujuan
                            </label>
                            <select
                                value={selectedPeriodId}
                                onChange={(e) => setSelectedPeriodId(e.target.value)}
                                className="w-full h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                {periods.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name} ({p.year}) - Status: {p.status}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <p className="text-xs text-muted-foreground bg-muted/40 p-2.5 rounded border border-border">
                            Setelah didaftarkan, seluruh materi pelatihan akan disiapkan untuk dievaluasi oleh reviewer pada periode tersebut.
                        </p>
                    </div>

                    <DialogFooter className="gap-2 sm:gap-0 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsEnrollModalOpen(false)}
                            disabled={isEnrolling}
                        >
                            Batal
                        </Button>
                        <Button
                            onClick={handleEnrollSubmit}
                            disabled={!selectedPeriodId || isEnrolling}
                        >
                            {isEnrolling ? 'Mendaftarkan...' : `Daftarkan (${selectedIds.length}) Pelatihan`}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
