<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public const PERMISSIONS_DATA = [
        'Manajemen Pelatihan' => [
            ['name' => 'view-trainings', 'label' => 'Melihat Pelatihan & Mata Pelatihan', 'description' => 'Melihat daftar dan detail pelatihan serta mata pelatihan.'],
            ['name' => 'manage-trainings', 'label' => 'Kelola Pelatihan & Mata Pelatihan', 'description' => 'Menambah, mengubah, dan menghapus pelatihan serta mata pelatihan.'],
            ['name' => 'assign-subjects', 'label' => 'Penugasan Mata Pelatihan', 'description' => 'Menugaskan pengembang materi dan reviewer ke mata pelatihan.'],
        ],
        'Materi Pembelajaran' => [
            ['name' => 'view-materials', 'label' => 'Melihat Materi Pembelajaran', 'description' => 'Melihat daftar materi dan pratinjau konten.'],
            ['name' => 'manage-materials', 'label' => 'Kelola Materi Pembelajaran', 'description' => 'Menambah, mengubah, dan menghapus materi.'],
            ['name' => 'upload-versions', 'label' => 'Unggah Versi Materi', 'description' => 'Mengunggah versi baru materi (SCORM ZIP / Google Drive).'],
            ['name' => 'delete-versions', 'label' => 'Hapus Versi Materi', 'description' => 'Menghapus versi materi dan berkas terkait.'],
        ],
        'Proses Reviu & Ulasan' => [
            ['name' => 'view-reviews', 'label' => 'Melihat Ulasan & Catatan', 'description' => 'Mengakses ruang kerja ulasan (workspace) dan melihat catatan.'],
            ['name' => 'create-reviews', 'label' => 'Membuat Catatan Reviu', 'description' => 'Menulis komentar ulasan materi dalam workspace.'],
            ['name' => 'reply-reviews', 'label' => 'Membalas Catatan Reviu', 'description' => 'Menulis balasan komentar dalam workspace.'],
            ['name' => 'resolve-reviews', 'label' => 'Tandai Catatan Selesai', 'description' => 'Mengubah status catatan ulasan menjadi selesai / addressed.'],
            ['name' => 'submit-reviews', 'label' => 'Submit Hasil Reviu', 'description' => 'Mengirimkan hasil evaluasi akhir reviu materi.'],
        ],
        'Laporan' => [
            ['name' => 'export-reports', 'label' => 'Ekspor Laporan (CSV)', 'description' => 'Mengunduh laporan rekapitulasi catatan reviu ke format CSV.'],
        ],
        'Pengguna & Sistem' => [
            ['name' => 'manage-users', 'label' => 'Manajemen Pengguna', 'description' => 'Menambah, mengubah, dan menghapus akun pengguna.'],
            ['name' => 'manage-permissions', 'label' => 'Pengelolaan Permissions', 'description' => 'Mengatur hak akses dan permissions bagi setiap role.'],
        ],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $allPermissionNames = [];

        foreach (self::PERMISSIONS_DATA as $group => $permissions) {
            foreach ($permissions as $item) {
                Permission::findOrCreate($item['name']);
                $allPermissionNames[] = $item['name'];
            }
        }

        // Roles
        $adminRole = Role::findOrCreate('admin');
        $developerRole = Role::findOrCreate('pengembang');
        $reviewerRole = Role::findOrCreate('reviewer');

        // Admin gets all permissions
        $adminRole->syncPermissions($allPermissionNames);

        // Developer permissions
        $developerPermissions = [
            'view-trainings',
            'view-materials',
            'manage-materials',
            'upload-versions',
            'view-reviews',
            'reply-reviews',
            'resolve-reviews',
        ];
        $developerRole->syncPermissions($developerPermissions);

        // Reviewer permissions
        $reviewerPermissions = [
            'view-trainings',
            'view-materials',
            'view-reviews',
            'create-reviews',
            'reply-reviews',
            'submit-reviews',
        ];
        $reviewerRole->syncPermissions($reviewerPermissions);
    }
}
