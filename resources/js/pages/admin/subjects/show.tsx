import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { 
    ArrowLeft, Plus, MoreVertical, Edit, Trash, FileText, 
    Users, UserPlus, UserCheck, ShieldCheck, Trash2, Mail, Building, IdCard 
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { 
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle 
} from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { toast } from 'sonner';
import { useEffect, useState } from 'react';
import type { SharedData } from '@/types';

type User = {
    id: number;
    name: string;
    email: string;
    nip?: string | null;
    unit_kerja?: string | null;
};

type Material = {
    id: number;
    title: string;
    description: string | null;
    type: string;
    order: number;
    versions: any[];
};

type Training = {
    id: number;
    title: string;
};

type Subject = {
    id: number;
    title: string;
    code?: string | null;
    jp?: number | null;
    description: string | null;
    training: Training;
    materials: Material[];
    developers: User[];
    reviewers: User[];
};

type Props = {
    subject: Subject;
    availableDevelopers: User[];
    availableReviewers: User[];
};

export default function Show({ subject, availableDevelopers = [], availableReviewers = [] }: Props) {
    const { flash } = usePage<{ flash?: { success?: string; error?: string } } & SharedData>().props;
    
    const [isDeveloperDialogOpen, setIsDeveloperDialogOpen] = useState(false);
    const [isReviewerDialogOpen, setIsReviewerDialogOpen] = useState(false);

    const developerForm = useForm({
        user_id: '',
        role: 'pengembang',
    });

    const reviewerForm = useForm({
        user_id: '',
        role: 'reviewer',
    });

    const deleteForm = useForm({});

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    // Filter available developers who are not yet assigned
    const assignedDeveloperIds = new Set(subject.developers?.map((u) => u.id) || []);
    const unassignedDevelopers = availableDevelopers.filter((u) => !assignedDeveloperIds.has(u.id));

    // Filter available reviewers who are not yet assigned
    const assignedReviewerIds = new Set(subject.reviewers?.map((u) => u.id) || []);
    const unassignedReviewers = availableReviewers.filter((u) => !assignedReviewerIds.has(u.id));

    const handleAssignDeveloper = (e: React.FormEvent) => {
        e.preventDefault();
        if (!developerForm.data.user_id) {
            toast.error('Silakan pilih pengembang materi terlebih dahulu.');
            return;
        }

        developerForm.post(`/admin/subjects/${subject.id}/assign`, {
            preserveScroll: true,
            onSuccess: () => {
                setIsDeveloperDialogOpen(false);
                developerForm.reset();
            },
        });
    };

    const handleAssignReviewer = (e: React.FormEvent) => {
        e.preventDefault();
        if (!reviewerForm.data.user_id) {
            toast.error('Silakan pilih reviewer terlebih dahulu.');
            return;
        }

        reviewerForm.post(`/admin/subjects/${subject.id}/assign`, {
            preserveScroll: true,
            onSuccess: () => {
                setIsReviewerDialogOpen(false);
                reviewerForm.reset();
            },
        });
    };

    const handleUnassign = (userId: number, role: 'pengembang' | 'reviewer', userName: string) => {
        const roleLabel = role === 'pengembang' ? 'Pengembang Materi' : 'Reviewer';
        if (confirm(`Apakah Anda yakin ingin mencabut penugasan ${userName} sebagai ${roleLabel}?`)) {
            deleteForm.delete(`/admin/subjects/${subject.id}/assign/${userId}?role=${role}`, {
                preserveScroll: true,
            });
        }
    };

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((n) => n[0])
            .slice(0, 2)
            .join('')
            .toUpperCase();
    };

    return (
        <>
            <Head title={`Mata Pelatihan: ${subject.title}`} />
            <div className="flex flex-col gap-6 p-6">
                {/* Header Section */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="icon" asChild>
                            <Link href={`/admin/trainings/${subject.training.id}`}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <div className="flex items-center gap-2 mb-1">
                                <span className="text-sm text-primary font-medium">{subject.training.title}</span>
                                {subject.code && (
                                    <Badge variant="outline" className="text-xs">
                                        Kode: {subject.code}
                                    </Badge>
                                )}
                                {subject.jp && (
                                    <Badge variant="secondary" className="text-xs">
                                        {subject.jp} JP
                                    </Badge>
                                )}
                            </div>
                            <h1 className="text-2xl font-semibold text-foreground">{subject.title}</h1>
                            <p className="text-sm text-muted-foreground mt-1">
                                {subject.description || 'Tidak ada deskripsi.'}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Team Assignment Section (Pengembang & Reviewer) */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Card Pengembang Materi */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
                            <div>
                                <CardTitle className="text-lg font-semibold flex items-center gap-2">
                                    <UserCheck className="h-5 w-5 text-blue-600" />
                                    Pengembang Materi
                                    <Badge variant="secondary" className="ml-1">
                                        {subject.developers?.length || 0}
                                    </Badge>
                                </CardTitle>
                                <CardDescription className="mt-1">
                                    Pengembang yang bertanggung jawab menyusun konten.
                                </CardDescription>
                            </div>
                            <Button 
                                size="sm" 
                                variant="outline"
                                onClick={() => {
                                    developerForm.setData('user_id', '');
                                    setIsDeveloperDialogOpen(true);
                                }}
                            >
                                <UserPlus className="h-4 w-4 mr-1.5" />
                                Tugaskan
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {!subject.developers || subject.developers.length === 0 ? (
                                <div className="text-center py-6 px-4 border border-dashed rounded-lg bg-muted/20 text-muted-foreground text-sm">
                                    <Users className="h-8 w-8 mx-auto mb-2 opacity-40" />
                                    Belum ada pengembang materi yang ditugaskan.
                                </div>
                            ) : (
                                <div className="divide-y divide-border">
                                    {subject.developers.map((user) => (
                                        <div key={user.id} className="py-3 flex items-center justify-between first:pt-0 last:pb-0">
                                            <div className="flex items-center gap-3">
                                                <Avatar className="h-9 w-9 bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300">
                                                    <AvatarFallback className="font-semibold text-xs">
                                                        {getInitials(user.name)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div>
                                                    <p className="text-sm font-medium text-foreground">{user.name}</p>
                                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground mt-0.5">
                                                        <span className="flex items-center gap-1">
                                                            <Mail className="h-3 w-3" /> {user.email}
                                                        </span>
                                                        {user.unit_kerja && (
                                                            <span className="flex items-center gap-1">
                                                                <Building className="h-3 w-3" /> {user.unit_kerja}
                                                            </span>
                                                        )}
                                                        {user.nip && (
                                                            <span className="flex items-center gap-1">
                                                                <IdCard className="h-3 w-3" /> {user.nip}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-muted-foreground hover:text-destructive h-8 w-8"
                                                title="Cabut Penugasan"
                                                onClick={() => handleUnassign(user.id, 'pengembang', user.name)}
                                                disabled={deleteForm.processing}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Card Reviewer */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
                            <div>
                                <CardTitle className="text-lg font-semibold flex items-center gap-2">
                                    <ShieldCheck className="h-5 w-5 text-emerald-600" />
                                    Reviewer
                                    <Badge variant="secondary" className="ml-1">
                                        {subject.reviewers?.length || 0}
                                    </Badge>
                                </CardTitle>
                                <CardDescription className="mt-1">
                                    Reviewer yang mengevaluasi dan memberi catatan materi.
                                </CardDescription>
                            </div>
                            <Button 
                                size="sm" 
                                variant="outline"
                                onClick={() => {
                                    reviewerForm.setData('user_id', '');
                                    setIsReviewerDialogOpen(true);
                                }}
                            >
                                <UserPlus className="h-4 w-4 mr-1.5" />
                                Tugaskan
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {!subject.reviewers || subject.reviewers.length === 0 ? (
                                <div className="text-center py-6 px-4 border border-dashed rounded-lg bg-muted/20 text-muted-foreground text-sm">
                                    <Users className="h-8 w-8 mx-auto mb-2 opacity-40" />
                                    Belum ada reviewer yang ditugaskan.
                                </div>
                            ) : (
                                <div className="divide-y divide-border">
                                    {subject.reviewers.map((user) => (
                                        <div key={user.id} className="py-3 flex items-center justify-between first:pt-0 last:pb-0">
                                            <div className="flex items-center gap-3">
                                                <Avatar className="h-9 w-9 bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                                                    <AvatarFallback className="font-semibold text-xs">
                                                        {getInitials(user.name)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div>
                                                    <p className="text-sm font-medium text-foreground">{user.name}</p>
                                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground mt-0.5">
                                                        <span className="flex items-center gap-1">
                                                            <Mail className="h-3 w-3" /> {user.email}
                                                        </span>
                                                        {user.unit_kerja && (
                                                            <span className="flex items-center gap-1">
                                                                <Building className="h-3 w-3" /> {user.unit_kerja}
                                                            </span>
                                                        )}
                                                        {user.nip && (
                                                            <span className="flex items-center gap-1">
                                                                <IdCard className="h-3 w-3" /> {user.nip}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-muted-foreground hover:text-destructive h-8 w-8"
                                                title="Cabut Penugasan"
                                                onClick={() => handleUnassign(user.id, 'reviewer', user.name)}
                                                disabled={deleteForm.processing}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Materials List Section */}
                <div className="flex items-center justify-between mt-2">
                    <h2 className="text-xl font-semibold flex items-center">
                        <FileText className="mr-2 h-5 w-5" />
                        Daftar Materi
                    </h2>
                    <Button asChild>
                        <Link href={`/admin/subjects/${subject.id}/materials/create`}>
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah Materi
                        </Link>
                    </Button>
                </div>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground border-b border-border">
                                <tr>
                                    <th className="font-medium p-4">Urutan</th>
                                    <th className="font-medium p-4">Tipe</th>
                                    <th className="font-medium p-4">Judul Materi</th>
                                    <th className="font-medium p-4">Jumlah Versi</th>
                                    <th className="font-medium p-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {!subject.materials || subject.materials.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="p-8 text-center text-muted-foreground">
                                            Belum ada materi pada mata pelatihan ini.
                                        </td>
                                    </tr>
                                ) : (
                                    subject.materials.map((material) => (
                                        <tr key={material.id} className="hover:bg-muted/50 transition-colors">
                                            <td className="p-4">{material.order}</td>
                                            <td className="p-4">
                                                <Badge variant="outline">{material.type.toUpperCase()}</Badge>
                                            </td>
                                            <td className="p-4">
                                                <Link href={`/admin/materials/${material.id}`} className="font-medium text-primary hover:underline">
                                                    {material.title}
                                                </Link>
                                            </td>
                                            <td className="p-4">
                                                {material.versions?.length || 0}
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
                                                            <Link href={`/admin/materials/${material.id}`}>
                                                                <FileText className="mr-2 h-4 w-4" />
                                                                Detail & Versi
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild>
                                                            <Link href={`/admin/materials/${material.id}/edit`}>
                                                                <Edit className="mr-2 h-4 w-4" />
                                                                Edit
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild>
                                                            <Link 
                                                                href={`/admin/materials/${material.id}`}
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
                </Card>
            </div>

            {/* Modal Dialog: Tugaskan Pengembang Materi */}
            <Dialog open={isDeveloperDialogOpen} onOpenChange={setIsDeveloperDialogOpen}>
                <DialogContent>
                    <form onSubmit={handleAssignDeveloper}>
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <UserCheck className="h-5 w-5 text-blue-600" />
                                Tugaskan Pengembang Materi
                            </DialogTitle>
                            <DialogDescription>
                                Pilih pengguna dengan role Pengembang Materi untuk ditugaskan pada mata pelatihan <strong>{subject.title}</strong>.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4 py-4">
                            <div className="space-y-2">
                                <Label htmlFor="developer-select">Pilih Pengembang Materi</Label>
                                {unassignedDevelopers.length === 0 ? (
                                    <p className="text-sm text-muted-foreground p-3 border rounded-md bg-muted/40">
                                        Tidak ada pengembang materi yang tersedia untuk ditugaskan (semua pengembang sudah ditugaskan atau belum ada akun pengembang aktif).
                                    </p>
                                ) : (
                                    <Select 
                                        value={developerForm.data.user_id} 
                                        onValueChange={(val) => developerForm.setData('user_id', val)}
                                    >
                                        <SelectTrigger id="developer-select" className="w-full">
                                            <SelectValue placeholder="Pilih pengembang materi..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {unassignedDevelopers.map((u) => (
                                                <SelectItem key={u.id} value={u.id.toString()}>
                                                    {u.name} {u.unit_kerja ? `(${u.unit_kerja})` : `(${u.email})`}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                                {developerForm.errors.user_id && (
                                    <p className="text-sm text-destructive">{developerForm.errors.user_id}</p>
                                )}
                            </div>
                        </div>

                        <DialogFooter>
                            <Button 
                                type="button" 
                                variant="outline" 
                                onClick={() => setIsDeveloperDialogOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button 
                                type="submit" 
                                disabled={developerForm.processing || unassignedDevelopers.length === 0 || !developerForm.data.user_id}
                            >
                                {developerForm.processing ? 'Menugaskan...' : 'Tugaskan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal Dialog: Tugaskan Reviewer */}
            <Dialog open={isReviewerDialogOpen} onOpenChange={setIsReviewerDialogOpen}>
                <DialogContent>
                    <form onSubmit={handleAssignReviewer}>
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <ShieldCheck className="h-5 w-5 text-emerald-600" />
                                Tugaskan Reviewer
                            </DialogTitle>
                            <DialogDescription>
                                Pilih pengguna dengan role Reviewer untuk ditugaskan pada mata pelatihan <strong>{subject.title}</strong>.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4 py-4">
                            <div className="space-y-2">
                                <Label htmlFor="reviewer-select">Pilih Reviewer</Label>
                                {unassignedReviewers.length === 0 ? (
                                    <p className="text-sm text-muted-foreground p-3 border rounded-md bg-muted/40">
                                        Tidak ada reviewer yang tersedia untuk ditugaskan (semua reviewer sudah ditugaskan atau belum ada akun reviewer aktif).
                                    </p>
                                ) : (
                                    <Select 
                                        value={reviewerForm.data.user_id} 
                                        onValueChange={(val) => reviewerForm.setData('user_id', val)}
                                    >
                                        <SelectTrigger id="reviewer-select" className="w-full">
                                            <SelectValue placeholder="Pilih reviewer..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {unassignedReviewers.map((u) => (
                                                <SelectItem key={u.id} value={u.id.toString()}>
                                                    {u.name} {u.unit_kerja ? `(${u.unit_kerja})` : `(${u.email})`}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                                {reviewerForm.errors.user_id && (
                                    <p className="text-sm text-destructive">{reviewerForm.errors.user_id}</p>
                                )}
                            </div>
                        </div>

                        <DialogFooter>
                            <Button 
                                type="button" 
                                variant="outline" 
                                onClick={() => setIsReviewerDialogOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button 
                                type="submit" 
                                disabled={reviewerForm.processing || unassignedReviewers.length === 0 || !reviewerForm.data.user_id}
                            >
                                {reviewerForm.processing ? 'Menugaskan...' : 'Tugaskan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
