import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { 
    CalendarRange, 
    Plus, 
    Edit, 
    Trash, 
    CheckCircle2, 
    Clock, 
    Layers, 
    Search, 
    X, 
    RotateCcw,
    BookOpen,
    FolderPlus,
    FileCheck
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
    status: string;
    year?: number | null;
    materials_count?: number;
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
    created_at: string;
};

type Props = {
    periods: {
        data: ReviewPeriod[];
        current_page: number;
        from: number | null;
        last_page: number;
        links: PaginationLink[];
        per_page: number;
        to: number | null;
        total: number;
    };
    allTrainings: Training[];
    filters?: {
        search?: string;
        status?: string;
        year?: string;
    };
    statuses?: Array<{ value: string; label: string }>;
};

export default function Index({
    periods,
    allTrainings = [],
    filters = {},
    statuses = [
        { value: 'draft', label: 'Draft' },
        { value: 'active', label: 'Aktif' },
        { value: 'closed', label: 'Ditutup' }
    ]
}: Props) {
    const { flash } = usePage<{ flash?: { success?: string; error?: string } } & SharedData>().props;
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [yearFilter, setYearFilter] = useState(filters.year || '');

    // Dialog state
    const [isPeriodModalOpen, setIsPeriodModalOpen] = useState(false);
    const [editingPeriod, setEditingPeriod] = useState<ReviewPeriod | null>(null);

    // Enroll Dialog state
    const [isEnrollModalOpen, setIsEnrollModalOpen] = useState(false);
    const [enrollingPeriod, setEnrollingPeriod] = useState<ReviewPeriod | null>(null);
    const [selectedTrainingIds, setSelectedTrainingIds] = useState<number[]>([]);
    const [trainingSearch, setTrainingSearch] = useState('');

    const periodForm = useForm({
        name: '',
        year: new Date().getFullYear(),
        start_date: '',
        end_date: '',
        status: 'active',
        description: '',
    });

    const enrollForm = useForm<{ training_ids: number[] }>({
        training_ids: [],
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/periods',
            { search: searchTerm, status: statusFilter, year: yearFilter },
            { preserveState: true, replace: true }
        );
    };

    const handleReset = () => {
        setSearchTerm('');
        setStatusFilter('');
        setYearFilter('');
        router.get('/admin/periods', {}, { preserveState: true, replace: true });
    };

    const openCreateModal = () => {
        setEditingPeriod(null);
        periodForm.setData({
            name: `Periode Reviu Kebijakan ${new Date().getFullYear()}`,
            year: new Date().getFullYear(),
            start_date: new Date().toISOString().split('T')[0],
            end_date: '',
            status: 'active',
            description: '',
        });
        setIsPeriodModalOpen(true);
    };

    const openEditModal = (period: ReviewPeriod) => {
        setEditingPeriod(period);
        periodForm.setData({
            name: period.name,
            year: period.year,
            start_date: period.start_date ? period.start_date.split('T')[0] : '',
            end_date: period.end_date ? period.end_date.split('T')[0] : '',
            status: period.status,
            description: period.description || '',
        });
        setIsPeriodModalOpen(true);
    };

    const handlePeriodSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingPeriod) {
            periodForm.put(`/admin/periods/${editingPeriod.id}`, {
                onSuccess: () => {
                    setIsPeriodModalOpen(false);
                    periodForm.reset();
                },
            });
        } else {
            periodForm.post('/admin/periods', {
                onSuccess: () => {
                    setIsPeriodModalOpen(false);
                    periodForm.reset();
                },
            });
        }
    };

    const handleDelete = (period: ReviewPeriod) => {
        if (confirm(`Apakah Anda yakin ingin menghapus periode "${period.name}"?`)) {
            router.delete(`/admin/periods/${period.id}`);
        }
    };

    const openEnrollModal = (period: ReviewPeriod) => {
        setEnrollingPeriod(period);
        setSelectedTrainingIds([]);
        setTrainingSearch('');
        setIsEnrollModalOpen(true);
    };

    const toggleTrainingSelect = (id: number) => {
        setSelectedTrainingIds(prev =>
            prev.includes(id) ? prev.filter(item => item !== id) : [...prev, id]
        );
    };

    const filteredTrainings = allTrainings.filter(t => 
        t.title.toLowerCase().includes(trainingSearch.toLowerCase())
    );

    const handleEnrollSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!enrollingPeriod || selectedTrainingIds.length === 0) return;

        enrollForm.setData('training_ids', selectedTrainingIds);
        router.post(
            `/admin/periods/${enrollingPeriod.id}/enroll`,
            { training_ids: selectedTrainingIds },
            {
                onSuccess: () => {
                    setIsEnrollModalOpen(false);
                    setSelectedTrainingIds([]);
                },
            }
        );
    };

    const hasActiveFilter = searchTerm !== '' || statusFilter !== '' || yearFilter !== '';

    return (
        <>
            <Head title="Manajemen Periode Reviu" />
            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                Siklus Reviu Berkala
                            </span>
                        </div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground mt-0.5">
                            Periode Reviu
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Kelola jadwal periode reviu berkala untuk mengevaluasi kepatuhan materi pelatihan terhadap kebijakan baru.
                        </p>
                    </div>

                    <Button onClick={openCreateModal} className="gap-2 self-start md:self-auto">
                        <Plus className="h-4 w-4" />
                        <span>Buat Periode Baru</span>
                    </Button>
                </div>

                {/* Filter Bar */}
                <Card className="p-4">
                    <form onSubmit={handleSearch} className="flex flex-wrap items-center gap-3">
                        <div className="relative flex-1 min-w-[200px]">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder="Cari nama periode..."
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

                        <div className="w-32">
                            <Input
                                type="number"
                                placeholder="Tahun"
                                value={yearFilter}
                                onChange={(e) => setYearFilter(e.target.value)}
                                className="h-9 text-xs"
                            />
                        </div>

                        <div className="w-36">
                            <select
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                                className="w-full h-9 rounded-md border border-input bg-background px-3 text-xs text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                            >
                                <option value="">Semua Status</option>
                                {statuses.map((s) => (
                                    <option key={s.value} value={s.value}>
                                        {s.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <Button type="submit" size="sm" className="h-9 gap-1.5">
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

                {/* Table Card */}
                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground border-b border-border">
                                <tr>
                                    <th className="font-medium p-4">Nama Periode &amp; Arahan</th>
                                    <th className="font-medium p-4 w-28 text-center">Tahun</th>
                                    <th className="font-medium p-4 w-44">Jadwal Reviu</th>
                                    <th className="font-medium p-4 w-32 text-center">Status</th>
                                    <th className="font-medium p-4 w-48 text-center">Progres Evaluasi</th>
                                    <th className="font-medium p-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {periods.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="p-12 text-center text-muted-foreground">
                                            <div className="flex flex-col items-center justify-center space-y-3">
                                                <div className="p-3 rounded-full bg-muted">
                                                    <CalendarRange className="h-6 w-6 text-muted-foreground" />
                                                </div>
                                                <div className="text-base font-medium text-foreground">
                                                    {hasActiveFilter
                                                        ? 'Tidak ada periode reviu yang sesuai filter.'
                                                        : 'Belum Ada Periode Reviu'}
                                                </div>
                                                <p className="text-xs text-muted-foreground max-w-sm">
                                                    {hasActiveFilter
                                                        ? 'Coba atur ulang kata kunci atau filter pencarian Anda.'
                                                        : 'Klik tombol "Buat Periode Baru" di atas untuk memulai siklus evaluasi materi.'}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    periods.data.map((period) => {
                                        const isCompleted = period.assignments_count > 0 && period.submitted_assignments_count === period.assignments_count;
                                        return (
                                            <tr key={period.id} className="hover:bg-muted/50 transition-colors">
                                                <td className="p-4">
                                                    <div className="flex flex-col gap-1">
                                                        <Link
                                                            href={`/admin/periods/${period.id}`}
                                                            className="font-semibold text-foreground text-sm hover:text-primary hover:underline"
                                                        >
                                                            {period.name}
                                                        </Link>
                                                        {period.description ? (
                                                            <p className="text-xs text-muted-foreground line-clamp-2 max-w-md">
                                                                {period.description}
                                                            </p>
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground italic">
                                                                Tidak ada catatan khusus
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="p-4 text-center align-middle font-mono font-medium">
                                                    {period.year}
                                                </td>
                                                <td className="p-4 align-middle text-xs text-muted-foreground">
                                                    {period.start_date || period.end_date ? (
                                                        <div className="flex flex-col gap-0.5">
                                                            <span>Mulai: {period.start_date ? new Date(period.start_date).toLocaleDateString('id-ID') : '-'}</span>
                                                            <span>Batas: {period.end_date ? new Date(period.end_date).toLocaleDateString('id-ID') : '-'}</span>
                                                        </div>
                                                    ) : (
                                                        <span>Fleksibel</span>
                                                    )}
                                                </td>
                                                <td className="p-4 text-center align-middle">
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
                                                        {period.status === 'active' ? 'Aktif' : period.status === 'closed' ? 'Ditutup' : 'Draft'}
                                                    </Badge>
                                                </td>
                                                <td className="p-4 text-center align-middle whitespace-nowrap">
                                                    {period.trainings_count === 0 ? (
                                                        <span className="text-xs text-muted-foreground italic">
                                                            Belum ada pelatihan
                                                        </span>
                                                    ) : (
                                                        <div className="inline-flex flex-col items-center gap-1">
                                                            <Badge
                                                                variant="outline"
                                                                className={
                                                                    isCompleted
                                                                        ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400 gap-1.5 py-1 px-2.5 text-xs'
                                                                        : period.submitted_assignments_count > 0
                                                                            ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-400 gap-1.5 py-1 px-2.5 text-xs'
                                                                            : 'border-muted-foreground/30 bg-muted/50 text-muted-foreground gap-1.5 py-1 px-2.5 text-xs'
                                                                }
                                                            >
                                                                {isCompleted ? (
                                                                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                                                                ) : (
                                                                    <FileCheck className="h-3.5 w-3.5" />
                                                                )}
                                                                <span>
                                                                    <strong className="font-semibold text-foreground">
                                                                        {period.submitted_assignments_count}
                                                                    </strong>
                                                                    <span className="opacity-70"> / {period.assignments_count}</span>
                                                                    <span className="ml-1 text-[11px]">tuntas</span>
                                                                </span>
                                                            </Badge>
                                                            <span className="text-[10px] text-muted-foreground">
                                                                {period.trainings_count} pelatihan terdaftar
                                                            </span>
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="p-4 text-right align-middle">
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        <Button
                                                            size="sm"
                                                            asChild
                                                            className="h-8 gap-1.5 text-xs shadow-sm"
                                                            title="Buka daftar pelatihan yang di-assign ke periode ini"
                                                        >
                                                            <Link href={`/admin/periods/${period.id}`}>
                                                                <BookOpen className="h-3.5 w-3.5" />
                                                                <span>Kelola Pelatihan</span>
                                                            </Link>
                                                        </Button>

                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => openEnrollModal(period)}
                                                            className="h-8 gap-1.5 text-xs text-primary border-primary/30 hover:bg-primary/5"
                                                            title="Daftarkan pelatihan ke periode ini"
                                                        >
                                                            <FolderPlus className="h-3.5 w-3.5" />
                                                            <span>Enroll Pelatihan</span>
                                                        </Button>

                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => openEditModal(period)}
                                                            className="h-8 w-8 p-0"
                                                            title="Edit Periode"
                                                        >
                                                            <Edit className="h-3.5 w-3.5" />
                                                        </Button>

                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => handleDelete(period)}
                                                            className="h-8 w-8 p-0 text-destructive border-destructive/30 hover:bg-destructive/10"
                                                            title="Hapus Periode"
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

                    <Pagination
                        links={periods.links}
                        from={periods.from}
                        to={periods.to}
                        total={periods.total}
                        itemName="periode reviu"
                    />
                </Card>

                {/* Create / Edit Dialog */}
                <Dialog open={isPeriodModalOpen} onOpenChange={setIsPeriodModalOpen}>
                    <DialogContent className="max-w-lg">
                        <DialogHeader>
                            <DialogTitle>
                                {editingPeriod ? 'Edit Periode Reviu' : 'Buat Periode Reviu Baru'}
                            </DialogTitle>
                            <DialogDescription>
                                Tentukan nama siklus, tahun pelaksanaan, dan arahan fokus kebijakan yang perlu dievaluasi oleh reviewer.
                            </DialogDescription>
                        </DialogHeader>

                        <form onSubmit={handlePeriodSubmit} className="space-y-4 py-2">
                            <div className="space-y-1.5">
                                <label className="text-xs font-semibold text-foreground">
                                    Nama Periode Reviu <span className="text-destructive">*</span>
                                </label>
                                <Input
                                    value={periodForm.data.name}
                                    onChange={(e) => periodForm.setData('name', e.target.value)}
                                    placeholder="Contoh: Periode Reviu Regulasi dan Kurikulum 2026"
                                    required
                                    className="text-xs"
                                />
                                {periodForm.errors.name && (
                                    <p className="text-xs text-destructive">{periodForm.errors.name}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold text-foreground">
                                        Tahun <span className="text-destructive">*</span>
                                    </label>
                                    <Input
                                        type="number"
                                        min={2020}
                                        max={2099}
                                        value={periodForm.data.year}
                                        onChange={(e) => periodForm.setData('year', parseInt(e.target.value) || 2026)}
                                        required
                                        className="text-xs"
                                    />
                                    {periodForm.errors.year && (
                                        <p className="text-xs text-destructive">{periodForm.errors.year}</p>
                                    )}
                                </div>

                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold text-foreground">
                                        Status Periode <span className="text-destructive">*</span>
                                    </label>
                                    <select
                                        value={periodForm.data.status}
                                        onChange={(e) => periodForm.setData('status', e.target.value)}
                                        className="w-full h-9 rounded-md border border-input bg-background px-3 text-xs text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                    >
                                        <option value="active">Aktif</option>
                                        <option value="draft">Draft</option>
                                        <option value="closed">Ditutup</option>
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold text-foreground">
                                        Tanggal Mulai
                                    </label>
                                    <Input
                                        type="date"
                                        value={periodForm.data.start_date}
                                        onChange={(e) => periodForm.setData('start_date', e.target.value)}
                                        className="text-xs"
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold text-foreground">
                                        Batas Waktu (Due Date)
                                    </label>
                                    <Input
                                        type="date"
                                        value={periodForm.data.end_date}
                                        onChange={(e) => periodForm.setData('end_date', e.target.value)}
                                        className="text-xs"
                                    />
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <label className="text-xs font-semibold text-foreground">
                                    Catatan / Fokus Regulasi Baru
                                </label>
                                <textarea
                                    rows={3}
                                    value={periodForm.data.description}
                                    onChange={(e) => periodForm.setData('description', e.target.value)}
                                    placeholder="Contoh: Evaluasi kesesuaian materi dengan PMK Nomor ... Tahun 2026 dan UU HKPD terbaru..."
                                    className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-xs shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                />
                            </div>

                            <DialogFooter className="pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setIsPeriodModalOpen(false)}
                                >
                                    Batal
                                </Button>
                                <Button type="submit" disabled={periodForm.processing}>
                                    {periodForm.processing ? 'Menyimpan...' : 'Simpan Periode'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                {/* Enroll Trainings Dialog */}
                <Dialog open={isEnrollModalOpen} onOpenChange={setIsEnrollModalOpen}>
                    <DialogContent className="max-w-2xl max-h-[85vh] flex flex-col">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <FolderPlus className="h-5 w-5 text-primary" />
                                <span>Daftarkan Pelatihan ke Periode Reviu</span>
                            </DialogTitle>
                            <DialogDescription>
                                Pilih pelatihan yang akan dievaluasi ulang pada periode: <strong className="text-foreground">{enrollingPeriod?.name}</strong>.
                                Sistem akan otomatis mengaktifkan status reviu materi dan menyiapkan lembar kerja bagi reviewer.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-3 py-2 flex-1 overflow-hidden flex flex-col">
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Cari judul pelatihan..."
                                    value={trainingSearch}
                                    onChange={(e) => setTrainingSearch(e.target.value)}
                                    className="pl-9 h-9 text-xs"
                                />
                            </div>

                            <div className="flex items-center justify-between text-xs text-muted-foreground px-1">
                                <span>{selectedTrainingIds.length} pelatihan terpilih</span>
                                <div className="space-x-2">
                                    <button
                                        type="button"
                                        onClick={() => setSelectedTrainingIds(filteredTrainings.map(t => t.id))}
                                        className="text-primary hover:underline"
                                    >
                                        Pilih Semua ({filteredTrainings.length})
                                    </button>
                                    <span>•</span>
                                    <button
                                        type="button"
                                        onClick={() => setSelectedTrainingIds([])}
                                        className="text-muted-foreground hover:underline"
                                    >
                                        Batal Pilih
                                    </button>
                                </div>
                            </div>

                            <div className="border rounded-md divide-y overflow-y-auto max-h-[350px]">
                                {filteredTrainings.length === 0 ? (
                                    <p className="p-4 text-center text-xs text-muted-foreground">
                                        Tidak ada pelatihan yang cocok dengan kata kunci pencarian.
                                    </p>
                                ) : (
                                    filteredTrainings.map((training) => {
                                        const isSelected = selectedTrainingIds.includes(training.id);
                                        return (
                                            <label
                                                key={training.id}
                                                className={`flex items-center justify-between p-3 cursor-pointer hover:bg-muted/50 transition-colors ${
                                                    isSelected ? 'bg-primary/5' : ''
                                                }`}
                                            >
                                                <div className="flex items-center gap-3">
                                                    <Checkbox
                                                        checked={isSelected}
                                                        onCheckedChange={() => toggleTrainingSelect(training.id)}
                                                    />
                                                    <div>
                                                        <div className="font-medium text-xs text-foreground">
                                                            {training.title}
                                                        </div>
                                                        <div className="text-[11px] text-muted-foreground flex items-center gap-2 mt-0.5">
                                                            <span>{training.materials_count ?? 0} materi</span>
                                                            <span>•</span>
                                                            <span>Status saat ini: {training.status}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </label>
                                        );
                                    })
                                )}
                            </div>
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsEnrollModalOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                onClick={handleEnrollSubmit}
                                disabled={selectedTrainingIds.length === 0}
                            >
                                Daftarkan ({selectedTrainingIds.length}) Pelatihan
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
