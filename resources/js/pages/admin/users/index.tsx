import { Head, Link, usePage } from '@inertiajs/react';
import { Plus, MoreVertical, Edit, Trash } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import { toast } from 'sonner';
import { useEffect } from 'react';
import type { SharedData } from '@/types';

type User = {
    id: number;
    name: string;
    email: string;
    nip: string | null;
    unit_kerja: string | null;
    is_active: boolean;
    created_at: string;
    roles: { id: number; name: string }[];
};

type Props = {
    users: {
        data: User[];
        links: any[];
    };
};

export default function Index({ users }: Props) {
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
            <Head title="Manajemen Pengguna" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Daftar Pengguna</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Kelola data pengguna, hak akses (role), dan unit kerja.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/admin/users/create">
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah Pengguna
                        </Link>
                    </Button>
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
                                            Belum ada data pengguna.
                                        </td>
                                    </tr>
                                ) : (
                                    users.data.map((user) => (
                                        <tr key={user.id} className="hover:bg-muted/50 transition-colors">
                                            <td className="p-4">
                                                <div className="font-medium text-foreground">{user.name}</div>
                                                <div className="text-muted-foreground">{user.email}</div>
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
                </Card>
            </div>
        </>
    );
}
