import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';

type Props = {
    roles: string[];
};

export default function Create({ roles }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        nip: '',
        unit_kerja: '',
        jabatan: '',
        pangkat_golongan: '',
        is_active: true,
        roles: [] as string[],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/users');
    };

    const handleRoleChange = (role: string, checked: boolean) => {
        if (checked) {
            setData('roles', [...data.roles, role]);
        } else {
            setData('roles', data.roles.filter(r => r !== role));
        }
    };

    return (
        <>
            <Head title="Tambah Pengguna" />
            <div className="flex flex-col gap-6 p-6 max-w-3xl mx-auto w-full">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href="/admin/users">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Tambah Pengguna</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Buat akun pengguna baru ke dalam sistem.
                        </p>
                    </div>
                </div>

                <Card>
                    <form onSubmit={submit}>
                        <CardHeader>
                            <CardTitle>Informasi Pengguna</CardTitle>
                            <CardDescription>
                                Masukkan profil dan hak akses pengguna.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Nama Lengkap</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="John Doe"
                                    />
                                    {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="john@example.com"
                                    />
                                    {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="phone">Nomor WhatsApp</Label>
                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        placeholder="081234567890"
                                    />
                                    {errors.phone && <p className="text-sm text-destructive">{errors.phone}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="nip">NIP</Label>
                                    <Input
                                        id="nip"
                                        value={data.nip}
                                        onChange={(e) => setData('nip', e.target.value)}
                                        placeholder="1980..."
                                    />
                                    {errors.nip && <p className="text-sm text-destructive">{errors.nip}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="unit_kerja">Unit Kerja</Label>
                                    <Input
                                        id="unit_kerja"
                                        value={data.unit_kerja}
                                        onChange={(e) => setData('unit_kerja', e.target.value)}
                                        placeholder="Pusat Pendidikan..."
                                    />
                                    {errors.unit_kerja && <p className="text-sm text-destructive">{errors.unit_kerja}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="jabatan">Jabatan</Label>
                                    <Input
                                        id="jabatan"
                                        value={data.jabatan}
                                        onChange={(e) => setData('jabatan', e.target.value)}
                                        placeholder="Pranata Komputer / Widyaiswara..."
                                    />
                                    {errors.jabatan && <p className="text-sm text-destructive">{errors.jabatan}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="pangkat_golongan">Pangkat / Golongan</Label>
                                    <Input
                                        id="pangkat_golongan"
                                        value={data.pangkat_golongan}
                                        onChange={(e) => setData('pangkat_golongan', e.target.value)}
                                        placeholder="Penata (III/c)..."
                                    />
                                    {errors.pangkat_golongan && <p className="text-sm text-destructive">{errors.pangkat_golongan}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="password">Password</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        placeholder="Minimal 8 karakter"
                                    />
                                    {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
                                </div>
                            </div>
                            
                            <div className="space-y-3 pt-4 border-t border-border">
                                <Label>Hak Akses (Role)</Label>
                                <div className="grid grid-cols-2 gap-2">
                                    {roles.map(role => (
                                        <div key={role} className="flex items-center space-x-2">
                                            <Checkbox 
                                                id={`role-${role}`} 
                                                checked={data.roles.includes(role)}
                                                onCheckedChange={(checked) => handleRoleChange(role, checked as boolean)}
                                            />
                                            <Label htmlFor={`role-${role}`} className="font-normal capitalize cursor-pointer">
                                                {role}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                                {errors.roles && <p className="text-sm text-destructive">{errors.roles}</p>}
                            </div>

                            <div className="space-y-3 pt-4 border-t border-border">
                                <div className="flex items-center space-x-2">
                                    <Checkbox 
                                        id="is_active" 
                                        checked={data.is_active}
                                        onCheckedChange={(checked) => setData('is_active', checked as boolean)}
                                    />
                                    <Label htmlFor="is_active" className="cursor-pointer">
                                        Akun Aktif (Dapat digunakan untuk login)
                                    </Label>
                                </div>
                                {errors.is_active && <p className="text-sm text-destructive">{errors.is_active}</p>}
                            </div>

                        </CardContent>
                        <div className="flex items-center justify-end border-t border-border p-4 bg-muted/20 rounded-b-xl">
                            <Button type="submit" disabled={processing}>
                                <Save className="mr-2 h-4 w-4" />
                                Simpan Pengguna
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </>
    );
}
