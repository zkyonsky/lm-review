<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as SpatieRole;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles
        foreach (Role::cases() as $role) {
            SpatieRole::findOrCreate($role->value);
        }

        $this->call(PermissionSeeder::class);

        // Admin User
        $admin = User::factory()->create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'nip' => '198001012005011001',
            'unit_kerja' => 'Pusdiklat',
        ]);
        $admin->assignRole(Role::Admin->value);

        // Developer User
        $developer = User::factory()->create([
            'name' => 'Pengembang Materi 1',
            'email' => 'pengembang@example.com',
            'password' => Hash::make('password'),
            'nip' => '198502022010011002',
            'unit_kerja' => 'Bidang Kurikulum',
        ]);
        $developer->assignRole(Role::Developer->value);

        // Reviewer User
        $reviewer1 = User::factory()->create([
            'name' => 'Reviewer Substansi',
            'email' => 'reviewer1@example.com',
            'password' => Hash::make('password'),
            'nip' => '197503032000011003',
            'unit_kerja' => 'Widyaiswara Ahli Madya',
        ]);
        $reviewer1->assignRole(Role::Reviewer->value);
        
        $reviewer2 = User::factory()->create([
            'name' => 'Reviewer Desain',
            'email' => 'reviewer2@example.com',
            'password' => Hash::make('password'),
            'nip' => '199004042015011004',
            'unit_kerja' => 'Pusat Teknologi Pembelajaran',
        ]);
        $reviewer2->assignRole(Role::Reviewer->value);

        $this->command->info('Database seeded successfully with demo users (password: password)');
    }
}
