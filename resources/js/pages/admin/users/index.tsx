import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Plus, MoreVertical, Edit, Trash, FileSpreadsheet, Download, Upload, Clock, Search, X, RotateCcw } from 'lucide-react';
import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import Pagination, { type PaginationLink } from '@/components/pagination';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { toast } from 'sonner';
import type { SharedData } from '@/types';

type User = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    nip: string | null;
    unit_kerja: string | null;
    is_active: boolean;
    created_at: string;
    roles: { id: number; name: string }[];
};

type Props = {
    users: {
        data: User[];
        links: PaginationLink[];
        from?: number | null;
        to?: number | null;
        total?: number;
    };
    filters?: {
        name?: string;
        role?: string;
    };
    roles?: string[];
};

export default function Index({ users, filters, roles }: Props) {
    const { flash } = usePage<{ flash?: { success?: string; error?: string; warning?: string } } & SharedData>().props;
    const [isImportModalOpen, setIsImportModalOpen] = useState(false);

    const [nameFilter, setNameFilter] = useState(filters?.name || '');
    const [roleFilter, setRoleFilter] = useState(filters?.role || '');

    useEffect(() => {
        setNameFilter(filters?.name || '');
        setRoleFilter(filters?.role || '');
    }, [filters?.name, filters?.role]);

    const hasActiveFilter = Boolean(nameFilter || roleFilter);

    const applyFilter = (newName: string, newRole: string) => {
        router.get(
            '/admin/users',
            {
                name: newName || undefined,
                role: newRole || undefined,
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
        applyFilter(nameFilter, roleFilter);
    };

    const handleRoleChange = (val: string) => {
        setRoleFilter(val);
        applyFilter(nameFilter, val);
    };

    const handleResetFilter = () => {
        setNameFilter('');
        setRoleFilter('');
        applyFilter('', '');
    };

    const { 
        data: importData, 
        setData: setImportData, 
        post: postImport, 
        processing: isImporting, 
        reset: resetImport, 
        errors: importErrors 
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
            toast.warning(flash.warning);
        }
    }, [flash]);

    const handleImportSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!importData.file) return;

        postImport('/admin/users/import', {
            onSuccess: () => {
                setIsImportModalOpen(false);
                resetImport();
            },
        });
    };

    return (
        <>
            <Head title="Manajemen Pengguna" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Daftar Pengguna</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Kelola data pengguna, hak akses (role), dan unit kerja.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setIsImportModalOpen(true)}
                            className="gap-1.5"
                        >
                            <FileSpreadsheet className="h-4 w-4 text-emerald-600 dark:text-emerald-500" />
                            <span>Import Excel</span>
                        </Button>
                        <Button asChild>
                            <Link href="/admin/users/create">
                                <Plus className="mr-2 h-4 w-4" />
                                Tambah Pengguna
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Filter Toolbar */}
                <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <form onSubmit={handleSearchSubmit} className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
                        <Input
                            placeholder="Cari berdasarkan nama, email, atau NIP..."
                            value={nameFilter}
                            onChange={(e) => setNameFilter(e.target.value)}
                            className="pl-9 pr-8 h-10 w-full bg-card"
                        />
                        {nameFilter && (
                            <button
                                type="button"
                                onClick={() => {
                                    setNameFilter('');
                                    applyFilter('', roleFilter);
                                }}
                                className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        )}
                    </form>

                    <div className="flex items-center gap-2">
                        <select
                            value={roleFilter}
                            onChange={(e) => handleRoleChange(e.target.value)}
                            aria-label="Filter peran pengguna"
                            className="h-10 min-w-[170px] rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 capitalize"
                        >
                            <option value="">Semua Peran</option>
                            {(roles && roles.length > 0 ? roles : ['admin', 'pengembang', 'reviewer']).map((r) => (
                                <option key={r} value={r}>
                                    {r === 'pengembang' ? 'Pengembang Materi' : r.charAt(0).toUpperCase() + r.slice(1)}
                                </option>
                            ))}
                        </select>

                        <Button 
                            type="button" 
                            variant="secondary" 
                            onClick={() => applyFilter(nameFilter, roleFilter)}
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

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground border-b border-border">
                                <tr>
                                    <th className="font-medium p-4">Nama / Email</th>
                                    <th className="font-medium p-4">NIP / Unit Kerja</th>
                                    <th className="font-medium p-4">Peran (Role)</th>
                                    <th className="font-medium p-4">Status</th>
                                    <th className="font-medium p-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {users.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="p-8 text-center text-muted-foreground">
                                            {hasActiveFilter 
                                                ? 'Tidak ada pengguna yang cocok dengan filter pencarian.' 
                                                : 'Belum ada data pengguna.'}
                                        </td>
                                    </tr>
                                ) : (
                                    users.data.map((user) => (
                                        <tr key={user.id} className="hover:bg-muted/50 transition-colors">
                                            <td className="p-4">
                                                <div className="font-medium text-foreground">{user.name}</div>
                                                <div className="text-muted-foreground">{user.email}</div>
                                                {user.phone && (
                                                    <div className="text-xs text-muted-foreground flex items-center gap-1 mt-0.5">
                                                        <span className="text-green-600 dark:text-green-400 font-medium">WA:</span> {user.phone}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="p-4">
                                                <div className="font-medium">{user.nip || '-'}</div>
                                                <div className="text-muted-foreground">{user.unit_kerja || '-'}</div>
                                            </td>
                                            <td className="p-4">
                                                <div className="flex flex-wrap gap-1">
                                                    {user.roles.length > 0 ? (
                                                        user.roles.map(role => (
                                                            <Badge key={role.id} variant="secondary">
                                                                {role.name}
                                                            </Badge>
                                                        ))
                                                    ) : (
                                                        <span className="text-muted-foreground">-</span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="p-4">
                                                {user.is_active ? (
                                                    <Badge className="bg-green-500 hover:bg-green-600">Aktif</Badge>
                                                ) : (
                                                    <Badge variant="destructive">Nonaktif</Badge>
                                                )}
                                            </td>
                                            <td className="p-4 text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="icon">
                                                            <MoreVertical className="h-4 w-4" />
                                                            <span className="sr-only">Menu</span>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem asChild>
                                                            <Link href={`/admin/users/${user.id}/edit`}>
                                                                <Edit className="mr-2 h-4 w-4" />
                                                                Edit
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild>
                                                            <Link 
                                                                href={`/admin/users/${user.id}`}
                                                                method="delete"
                                                                as="button"
                                                                className="text-destructive focus:text-destructive w-full"
                                                            >
                                                                <Trash className="mr-2 h-4 w-4" />
                                                                Hapus
                                                            </Link>
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    <Pagination
                        links={users.links}
                        from={users.from}
                        to={users.to}
                        total={users.total}
                        itemName="pengguna"
                    />
                </Card>
            </div>

            {/* Modal Import Massal Pengguna (Excel) */}
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
                            <FileSpreadsheet className="h-5 w-5 text-emerald-600 dark:text-emerald-500" />
                            Import Massal Data Pengguna
                        </DialogTitle>
                        <DialogDescription>
                            Unggah berkas Excel (.xlsx, .xls) atau CSV (.csv) untuk menambahkan atau memperbarui data akun pengguna dan peran secara massal.
                        </DialogDescription>
                    </DialogHeader>

                    {/* Step 1: Download Template */}
                    <div className="rounded-lg border bg-muted/40 p-4 space-y-2.5">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <h4 className="text-sm font-semibold text-foreground">1. Unduh Template Excel</h4>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Gunakan template resmi yang telah dilengkapi validasi dropdown peran dan status.
                                </p>
                            </div>
                            <Button variant="secondary" size="sm" asChild className="gap-1.5 shrink-0 self-start sm:self-auto">
                                <a href="/admin/users/import/template" download>
                                    <Download className="h-3.5 w-3.5" />
                                    <span>Unduh Template</span>
                                </a>
                            </Button>
                        </div>
                        <div className="text-xs text-muted-foreground pt-2 border-t border-border/60 space-y-1">
                            <div>
                                <span className="font-medium text-foreground">Kolom yang didukung: </span>
                                Nama Lengkap, Email, Password, Peran (pengembang/reviewer/admin), NIP, No. WhatsApp, Unit Kerja, Jabatan, Pangkat/Golongan, Status (Aktif/Nonaktif).
                            </div>
                            <div className="text-[11px] text-muted-foreground italic">
                                * Catatan: Pengguna dicocokkan berdasarkan email. Jika sudah terdaftar, data akan diperbarui. Jika kolom password kosong, kata sandi bawaan adalah &quot;password&quot; (untuk akun baru).
                            </div>
                        </div>
                    </div>

                    {/* Step 2: Upload Form */}
                    <form onSubmit={handleImportSubmit} className="space-y-4 pt-1">
                        <div className="space-y-1.5">
                            <label className="text-sm font-semibold text-foreground">
                                2. Pilih Berkas Excel / CSV
                            </label>
                            <div className="relative border-2 border-dashed border-border hover:border-emerald-500/50 transition-colors rounded-lg p-6 text-center bg-card flex flex-col items-center justify-center gap-2">
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
                                        <div className="p-2.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">
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
                                            Klik untuk memilih berkas atau seret berkas ke sini
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Format: .xlsx, .xls, .csv (Maksimal 10MB)
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
                                className="gap-2 bg-emerald-600 hover:bg-emerald-700 text-white"
                            >
                                {isImporting ? (
                                    <>
                                        <Clock className="h-4 w-4 animate-spin" />
                                        <span>Memproses Impor...</span>
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
        </>
    );
}
