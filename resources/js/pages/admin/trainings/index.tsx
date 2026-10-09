import { Head, Link, router, usePage } from '@inertiajs/react';
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
    RotateCcw
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import Pagination, { type PaginationLink } from '@/components/pagination';
import { toast } from 'sonner';
import { useEffect, useState } from 'react';
import type { SharedData } from '@/types';

type Training = {
    id: number;
    code: string;
    title: string;
    description: string | null;
    status: string;
    created_at: string;
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
    };
    statuses?: string[];
};

export default function Index({ 
    trainings, 
    filters = {}, 
    statuses = ['Sedang Review', 'Selesai Review'] 
}: Props) {
    const { flash } = usePage<{ flash?: { success?: string; error?: string } } & SharedData>().props;
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [isUpdatingStatus, setIsUpdatingStatus] = useState(false);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

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

    const applyFilter = (newSearch: string, newStatus: string) => {
        router.get(
            '/admin/trainings',
            {
                search: newSearch || undefined,
                status: newStatus || undefined,
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
        applyFilter(searchTerm, statusFilter);
    };

    const handleStatusFilterChange = (val: string) => {
        setStatusFilter(val);
        applyFilter(searchTerm, val);
    };

    const handleResetFilter = () => {
        setSearchTerm('');
        setStatusFilter('');
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

    const hasActiveFilter = Boolean(filters.search || filters.status);

    return (
        <>
            <Head title="Manajemen Pelatihan" />
            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Daftar Pelatihan</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Kelola data pelatihan, status penelaahan, mata pelatihan, dan dokumen STMK.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/admin/trainings/create">
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah Pelatihan
                        </Link>
                    </Button>
                </div>

                {/* Filter and Search Bar */}
                <div className="flex flex-col md:flex-row items-stretch md:items-center gap-3">
                    <form onSubmit={handleSearchSubmit} className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
                        <Input
                            placeholder="Cari nama atau kode pelatihan..."
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

                    <div className="flex items-center gap-2">
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
                            {/* Ubah Status Massal */}
                            <span className="text-xs text-muted-foreground font-medium hidden md:inline ml-1">
                                Ubah Status:
                            </span>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={isUpdatingStatus}
                                onClick={() => handleBulkStatusChange('Sedang Review')}
                                className="h-8 border-amber-300 text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-400 dark:hover:bg-amber-950/30 gap-1.5"
                            >
                                <Clock className="h-3.5 w-3.5" />
                                Set: Sedang Review
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={isUpdatingStatus}
                                onClick={() => handleBulkStatusChange('Selesai Review')}
                                className="h-8 border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-950/30 gap-1.5"
                            >
                                <CheckCircle2 className="h-3.5 w-3.5" />
                                Set: Selesai Review
                            </Button>

                            {/* Separator */}
                            <div className="h-4 w-px bg-border mx-1 hidden sm:block" />

                            {/* STMK Massal */}
                            <span className="text-xs text-muted-foreground font-medium hidden md:inline">
                                STMK:
                            </span>
                            <Button
                                size="sm"
                                onClick={() => handleBulkDownload('pengembangan')}
                                className="h-8 bg-blue-600 hover:bg-blue-700 text-white gap-1.5"
                            >
                                <FileText className="h-3.5 w-3.5" />
                                STMK Pengembang
                            </Button>
                            <Button
                                size="sm"
                                onClick={() => handleBulkDownload('reviu')}
                                className="h-8 bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5"
                            >
                                <FileCheck className="h-3.5 w-3.5" />
                                STMK Reviu
                            </Button>

                            <Button
                                variant="ghost"
                                size="sm"
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
                                    <th className="font-medium p-4 w-32">Kode</th>
                                    <th className="font-medium p-4">Judul Pelatihan</th>
                                    <th className="font-medium p-4 w-36">Status</th>
                                    <th className="font-medium p-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {trainings.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="p-8 text-center text-muted-foreground">
                                            {hasActiveFilter
                                                ? 'Tidak ada pelatihan yang cocok dengan filter pencarian.'
                                                : 'Belum ada data pelatihan.'}
                                        </td>
                                    </tr>
                                ) : (
                                    trainings.data.map((training) => {
                                        const isDone = training.status === 'Selesai Review';
                                        return (
                                            <tr key={training.id} className="hover:bg-muted/50 transition-colors">
                                                <td className="p-4 align-middle">
                                                    <Checkbox
                                                        checked={selectedIds.includes(training.id)}
                                                        onCheckedChange={() => toggleSelect(training.id)}
                                                        aria-label={`Pilih ${training.title}`}
                                                    />
                                                </td>
                                                <td className="p-4 align-middle">
                                                    <Badge variant="outline" className="font-mono">
                                                        {training.code}
                                                    </Badge>
                                                </td>
                                                <td className="p-4">
                                                    <Link 
                                                        href={`/admin/trainings/${training.id}`} 
                                                        className="font-medium text-primary hover:underline"
                                                    >
                                                        {training.title}
                                                    </Link>
                                                    {training.description && (
                                                        <p className="text-xs text-muted-foreground line-clamp-1 mt-0.5">
                                                            {training.description}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="p-4 align-middle">
                                                    <Badge
                                                        className={
                                                            isDone
                                                                ? 'bg-emerald-500 hover:bg-emerald-600 text-white font-medium'
                                                                : 'bg-amber-500 hover:bg-amber-600 text-white font-medium'
                                                        }
                                                    >
                                                        {training.status || 'Sedang Review'}
                                                    </Badge>
                                                </td>
                                                <td className="p-4 text-right">
                                                    <div className="flex flex-wrap items-center justify-end gap-1.5">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            asChild
                                                            className="h-8 border-blue-200 text-blue-700 hover:bg-blue-50 dark:border-blue-900/50 dark:text-blue-400 dark:hover:bg-blue-950/30"
                                                        >
                                                            <a
                                                                href={`/admin/trainings/${training.id}/stmk/pengembangan`}
                                                                download
                                                                title="Generate STMK Pengembang"
                                                            >
                                                                <FileText className="mr-1.5 h-3.5 w-3.5" />
                                                                STMK Pengembang
                                                            </a>
                                                        </Button>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            asChild
                                                            className="h-8 border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900/50 dark:text-emerald-400 dark:hover:bg-emerald-950/30"
                                                        >
                                                            <a
                                                                href={`/admin/trainings/${training.id}/stmk/reviu`}
                                                                download
                                                                title="Generate STMK Reviu"
                                                            >
                                                                <FileCheck className="mr-1.5 h-3.5 w-3.5" />
                                                                STMK Reviu
                                                            </a>
                                                        </Button>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            asChild
                                                            className="h-8 border-violet-200 text-violet-700 hover:bg-violet-50 dark:border-violet-900/50 dark:text-violet-400 dark:hover:bg-violet-950/30"
                                                        >
                                                            <a
                                                                href={`/admin/trainings/${training.id}/report`}
                                                                download
                                                                title="Ekspor Laporan Komentar (CSV)"
                                                            >
                                                                <FileSpreadsheet className="mr-1.5 h-3.5 w-3.5" />
                                                                Laporan
                                                            </a>
                                                        </Button>
                                                        <Button variant="outline" size="sm" asChild className="h-8">
                                                            <Link href={`/admin/trainings/${training.id}`}>
                                                                <BookOpen className="mr-1.5 h-3.5 w-3.5" />
                                                                Detail & Materi
                                                            </Link>
                                                        </Button>
                                                        <Button variant="outline" size="sm" asChild className="h-8">
                                                            <Link href={`/admin/trainings/${training.id}/edit`}>
                                                                <Edit className="mr-1.5 h-3.5 w-3.5" />
                                                                Edit
                                                            </Link>
                                                        </Button>
                                                        <Button 
                                                            variant="outline" 
                                                            size="sm" 
                                                            asChild 
                                                            className="h-8 text-destructive hover:text-destructive hover:bg-destructive/10 border-destructive/30"
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
                                                                <Trash className="mr-1.5 h-3.5 w-3.5" />
                                                                Hapus
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
        </>
    );
}
