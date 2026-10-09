<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'label' => match ($role->name) {
                    'admin' => 'Administrator',
                    'pengembang' => 'Pengembang Materi',
                    'reviewer' => 'Reviewer',
                    default => ucfirst($role->name),
                },
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ];
        });

        // Group definitions from PermissionSeeder
        $metadataGroups = PermissionSeeder::PERMISSIONS_DATA;

        // Map all existing permissions from DB, organizing by group metadata
        $allPermissions = Permission::orderBy('name')->get();
        $groupedPermissions = [];

        // Track assigned permission names
        $assignedNames = [];

        foreach ($metadataGroups as $groupName => $items) {
            $groupList = [];
            foreach ($items as $item) {
                if ($allPermissions->firstWhere('name', $item['name'])) {
                    $groupList[] = $item;
                    $assignedNames[] = $item['name'];
                }
            }
            if (!empty($groupList)) {
                $groupedPermissions[$groupName] = $groupList;
            }
        }

        // Catch any custom or extra permissions not in predefined seeder groups
        $customPermissions = $allPermissions->whereNotIn('name', $assignedNames)->map(function ($p) {
            return [
                'name' => $p->name,
                'label' => ucwords(str_replace(['-', '_'], ' ', $p->name)),
                'description' => 'Izin kustom sistem.',
            ];
        })->values()->toArray();

        if (!empty($customPermissions)) {
            $groupedPermissions['Izin Lainnya'] = $customPermissions;
        }

        return Inertia::render('admin/permissions/index', [
            'roles' => $roles,
            'groupedPermissions' => $groupedPermissions,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $permissions = $validated['permissions'] ?? [];

        // Prevent admin from removing their own permission management capability
        if ($role->name === 'admin' && !in_array('manage-permissions', $permissions)) {
            $permissions[] = 'manage-permissions';
        }

        $role->syncPermissions($permissions);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return back()->with('success', "Hak akses (permissions) untuk role '{$role->name}' berhasil diperbarui.");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|regex:/^[a-z0-9\-]+$/|unique:permissions,name',
        ]);

        Permission::create(['name' => $validated['name']]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return back()->with('success', "Permission baru '{$validated['name']}' berhasil ditambahkan.");
    }
}
