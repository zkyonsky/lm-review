import { Head, useForm, usePage } from '@inertiajs/react';
import { 
    ShieldCheck, 
    Shield, 
    CheckSquare, 
    Square, 
    RotateCcw, 
    Save, 
    Plus, 
    Search,
    BookOpen,
    Layers,
    MessageSquare,
    FileSpreadsheet,
    Users,
    Key
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { toast } from 'sonner';
import { useEffect, useState, useMemo } from 'react';
import type { SharedData } from '@/types';

type RoleData = {
    id: number;
    name: string;
    label: string;
    permissions: string[];
};

type PermissionItem = {
    name: string;
    label: string;
    description: string;
};

type Props = {
    roles: RoleData[];
    groupedPermissions: Record<string, PermissionItem[]>;
};

const GROUP_ICONS: Record<string, any> = {
    'Manajemen Pelatihan': BookOpen,
    'Materi Pembelajaran': Layers,
    'Proses Reviu & Ulasan': MessageSquare,
    'Laporan': FileSpreadsheet,
    'Pengguna & Sistem': Users,
};

export default function Index({ roles, groupedPermissions }: Props) {
    const { flash } = usePage<{ flash?: { success?: string; error?: string } } & SharedData>().props;
    const [activeRoleId, setActiveRoleId] = useState<number>(roles[0]?.id ?? 1);
    const [searchQuery, setSearchQuery] = useState('');
    const [isCreateOpen, setIsCreateOpen] = useState(false);

    const activeRole = useMemo(() => {
        return roles.find((r) => r.id === activeRoleId) || roles[0];
    }, [roles, activeRoleId]);

    // Form for updating permissions
    const { data, setData, put, processing, isDirty } = useForm<{
        permissions: string[];
    }>({
        permissions: activeRole?.permissions || [],
    });

    // When changing selected role, update form state
    useEffect(() => {
        if (activeRole) {
            setData('permissions', activeRole.permissions || []);
        }
    }, [activeRole?.id]);

    // Flash toast
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    // Form for adding new custom permission
    const createForm = useForm({
        name: '',
    });

    const handleTogglePermission = (permissionName: string) => {
        const current = [...data.permissions];
        const index = current.indexOf(permissionName);
        if (index > -1) {
            current.splice(index, 1);
        } else {
            current.push(permissionName);
        }
        setData('permissions', current);
    };

    const handleSelectAll = () => {
        const allNames: string[] = [];
        Object.values(groupedPermissions).forEach((group) => {
            group.forEach((p) => allNames.push(p.name));
        });
        setData('permissions', allNames);
    };

    const handleDeselectAll = () => {
        // Keep manage-permissions for admin
        if (activeRole?.name === 'admin') {
            setData('permissions', ['manage-permissions']);
        } else {
            setData('permissions', []);
        }
    };

    const handleReset = () => {
        setData('permissions', activeRole?.permissions || []);
    };

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/roles-permissions/${activeRole.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                // Success handled by flash
            },
        });
    };

    const handleCreatePermission = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/admin/permissions', {
            onSuccess: () => {
                createForm.reset();
                setIsCreateOpen(false);
            },
        });
    };

    // Filter permissions by search
    const filteredGroupedPermissions = useMemo(() => {
        if (!searchQuery.trim()) return groupedPermissions;
        const q = searchQuery.toLowerCase();
        const result: Record<string, PermissionItem[]> = {};

        Object.entries(groupedPermissions).forEach(([groupName, items]) => {
            const matched = items.filter(
                (p) =>
                    p.name.toLowerCase().includes(q) ||
                    p.label.toLowerCase().includes(q) ||
                    p.description.toLowerCase().includes(q)
            );
            if (matched.length > 0) {
                result[groupName] = matched;
            }
        });
        return result;
    }, [groupedPermissions, searchQuery]);

    const totalPermissionsCount = useMemo(() => {
        return Object.values(groupedPermissions).reduce((acc, curr) => acc + curr.length, 0);
    }, [groupedPermissions]);

    return (
        <>
            <Head title="Pengelolaan Permissions Role" />
            <div className="flex flex-col gap-6 p-6 max-w-6xl mx-auto w-full">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <ShieldCheck className="h-6 w-6 text-primary" />
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                Pengelolaan Permissions
                            </h1>
                        </div>
                        <p className="text-sm text-muted-foreground mt-1">
                            Atur izin akses (permissions) dan kapabilitas untuk setiap role dalam aplikasi.
                        </p>
                    </div>

                    <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                        <DialogTrigger asChild>
                            <Button variant="outline">
                                <Plus className="mr-2 h-4 w-4" />
                                Tambah Izin Kustom
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <form onSubmit={handleCreatePermission}>
                                <DialogHeader>
                                    <DialogTitle>Tambah Permission Baru</DialogTitle>
                                    <DialogDescription>
                                        Gunakan format huruf kecil dengan tanda strip (kebab-case), contoh: <code>export-excel</code> atau <code>manage-categories</code>.
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="py-4 space-y-3">
                                    <Label htmlFor="perm_name">Nama Permission (Identifier)</Label>
                                    <Input
                                        id="perm_name"
                                        placeholder="contoh: audit-logs"
                                        value={createForm.data.name}
                                        onChange={(e) => createForm.setData('name', e.target.value.toLowerCase().trim())}
                                        required
                                    />
                                    {createForm.errors.name && (
                                        <p className="text-xs text-destructive">{createForm.errors.name}</p>
                                    )}
                                </div>
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => setIsCreateOpen(false)}
                                    >
                                        Batal
                                    </Button>
                                    <Button type="submit" disabled={createForm.processing || !createForm.data.name}>
                                        Simpan Permission
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                {/* Role Tabs */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    {roles.map((r) => {
                        const isSelected = r.id === activeRole.id;
                        const roleActiveCount = isSelected 
                            ? data.permissions.length 
                            : r.permissions.length;

                        return (
                            <button
                                key={r.id}
                                type="button"
                                onClick={() => setActiveRoleId(r.id)}
                                className={`text-left p-4 rounded-xl border transition-all cursor-pointer ${
                                    isSelected
                                        ? 'bg-primary/5 border-primary shadow-sm ring-1 ring-primary'
                                        : 'bg-card border-border hover:bg-muted/50 hover:border-muted-foreground/30'
                                }`}
                            >
                                <div className="flex items-center justify-between mb-2">
                                    <span className="font-semibold text-base flex items-center gap-2">
                                        <Shield className={`h-4 w-4 ${isSelected ? 'text-primary' : 'text-muted-foreground'}`} />
                                        {r.label}
                                    </span>
                                    <Badge variant={isSelected ? 'default' : 'secondary'} className="text-xs">
                                        {r.name}
                                    </Badge>
                                </div>
                                <div className="flex items-center justify-between text-xs text-muted-foreground">
                                    <span>Hak Akses Aktif:</span>
                                    <span className="font-medium text-foreground">
                                        {roleActiveCount} / {totalPermissionsCount} Izin
                                    </span>
                                </div>
                            </button>
                        );
                    })}
                </div>

                {/* Main Content Area */}
                <form onSubmit={handleSave} className="space-y-6">
                    {/* Action Bar & Search */}
                    <Card className="bg-card/70 border-border shadow-sm">
                        <CardContent className="p-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div className="relative w-full sm:w-72">
                                <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Cari izin akses..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-9 h-9 text-xs"
                                />
                            </div>

                            <div className="flex items-center gap-2 w-full sm:w-auto justify-end">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleSelectAll}
                                    className="text-xs h-8"
                                >
                                    <CheckSquare className="mr-1.5 h-3.5 w-3.5" />
                                    Pilih Semua
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleDeselectAll}
                                    className="text-xs h-8"
                                >
                                    <Square className="mr-1.5 h-3.5 w-3.5" />
                                    Hapus Semua
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={handleReset}
                                    className="text-xs h-8 text-muted-foreground"
                                >
                                    <RotateCcw className="mr-1.5 h-3.5 w-3.5" />
                                    Reset
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Permissions Groups */}
                    <div className="space-y-5">
                        {Object.entries(filteredGroupedPermissions).length === 0 ? (
                            <div className="text-center py-12 text-muted-foreground">
                                Tidak ada izin yang cocok dengan kata kunci &quot;{searchQuery}&quot;.
                            </div>
                        ) : (
                            Object.entries(filteredGroupedPermissions).map(([groupName, items]) => {
                                const GroupIcon = GROUP_ICONS[groupName] || Key;
                                const activeInGroup = items.filter((p) => data.permissions.includes(p.name)).length;

                                return (
                                    <Card key={groupName} className="overflow-hidden border-border shadow-sm">
                                        <CardHeader className="bg-muted/40 py-3.5 px-5 border-b border-border">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <GroupIcon className="h-4 w-4 text-primary" />
                                                    <CardTitle className="text-sm font-semibold text-foreground">
                                                        {groupName}
                                                    </CardTitle>
                                                </div>
                                                <Badge variant="outline" className="text-xs font-normal">
                                                    {activeInGroup} / {items.length} Aktif
                                                </Badge>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="p-0">
                                            <div className="divide-y divide-border">
                                                {items.map((perm) => {
                                                    const isChecked = data.permissions.includes(perm.name);
                                                    return (
                                                        <label
                                                            key={perm.name}
                                                            htmlFor={`perm_${perm.name}`}
                                                            className={`flex items-start gap-3 p-4 cursor-pointer transition-colors ${
                                                                isChecked
                                                                    ? 'bg-primary/[0.02] hover:bg-primary/[0.05]'
                                                                    : 'hover:bg-muted/30'
                                                            }`}
                                                        >
                                                            <Checkbox
                                                                id={`perm_${perm.name}`}
                                                                checked={isChecked}
                                                                onCheckedChange={() => handleTogglePermission(perm.name)}
                                                                className="mt-0.5"
                                                            />
                                                            <div className="flex-1 space-y-1">
                                                                <div className="flex items-center gap-2">
                                                                    <span className="text-sm font-medium text-foreground">
                                                                        {perm.label}
                                                                    </span>
                                                                    <code className="text-[11px] text-muted-foreground bg-muted px-1.5 py-0.5 rounded font-mono">
                                                                        {perm.name}
                                                                    </code>
                                                                </div>
                                                                <p className="text-xs text-muted-foreground">
                                                                    {perm.description}
                                                                </p>
                                                            </div>
                                                        </label>
                                                    );
                                                })}
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })
                        )}
                    </div>

                    {/* Bottom Save Bar */}
                    <div className="sticky bottom-4 z-10 bg-background/95 backdrop-blur border border-border p-4 rounded-xl shadow-lg flex items-center justify-between gap-4">
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium text-foreground">
                                Mengedit Role: <Badge variant="default">{activeRole.label}</Badge>
                            </span>
                            <span className="text-xs text-muted-foreground hidden sm:inline">
                                ({data.permissions.length} izin terpilih)
                            </span>
                        </div>

                        <Button type="submit" disabled={processing}>
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? 'Menyimpan...' : `Simpan Permissions (${activeRole.label})`}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
