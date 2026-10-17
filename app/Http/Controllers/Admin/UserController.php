<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserImportService;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles');

        if ($request->filled('name')) {
            $name = $request->input('name');
            $query->where(function ($q) use ($name) {
                $q->where('name', 'like', "%{$name}%")
                  ->orWhere('email', 'like', "%{$name}%")
                  ->orWhere('nip', 'like', "%{$name}%");
            });
        } elseif ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $role = $request->input('role');
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        $users = $query->latest()->paginate(10)->withQueryString();

        $roles = Role::orderBy('name')->pluck('name')->toArray();

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => [
                'name' => $request->input('name', $request->input('search', '')),
                'role' => $request->input('role', ''),
            ],
            'roles' => $roles,
        ]);
    }

    /**
     * Unduh template file Excel untuk impor massal pengguna.
     */
    public function downloadTemplate(UserImportService $importService): StreamedResponse
    {
        $spreadsheet = $importService->generateTemplate();

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $filename = 'Template_Import_Pengguna_' . date('Ymd') . '.xlsx';

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    /**
     * Memproses upload file Excel untuk impor massal pengguna.
     */
    public function import(Request $request, UserImportService $importService)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:10240', // 10MB
            ],
        ], [
            'file.required' => 'Silakan pilih berkas Excel untuk diunggah.',
            'file.mimes' => 'Format berkas harus berupa Excel (.xlsx, .xls) atau CSV (.csv).',
            'file.max' => 'Ukuran berkas maksimal adalah 10MB.',
        ]);

        try {
            $result = $importService->import($request->file('file'));

            $summary = sprintf(
                'Impor pengguna berhasil! %d pengguna baru ditambahkan dan %d pengguna diperbarui.',
                $result['users_created'],
                $result['users_updated']
            );

            if (!empty($result['warnings'])) {
                $warningSummary = implode("\n", array_slice($result['warnings'], 0, 5));
                if (count($result['warnings']) > 5) {
                    $warningSummary .= "\n...dan " . (count($result['warnings']) - 5) . " catatan lainnya.";
                }

                return redirect()->route('admin.users.index')
                    ->with('success', $summary)
                    ->with('warning', 'Beberapa catatan impor: ' . $warningSummary);
            }

            return redirect()->route('admin.users.index')->with('success', $summary);
        } catch (\Throwable $e) {
            return redirect()->route('admin.users.index')->with('error', 'Gagal memproses berkas impor: ' . $e->getMessage());
        }
    }

    public function create()
    {
        $roles = Role::pluck('name');
        return Inertia::render('admin/users/create', [
            'roles' => $roles
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:30',
            'password' => ['required', Rules\Password::defaults()],
            'nip' => 'nullable|string|max:50',
            'unit_kerja' => 'nullable|string|max:255',
            'jabatan' => 'nullable|string|max:255',
            'pangkat_golongan' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'roles' => 'array'
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'nip' => $validated['nip'] ?? null,
            'unit_kerja' => $validated['unit_kerja'] ?? null,
            'jabatan' => $validated['jabatan'] ?? null,
            'pangkat_golongan' => $validated['pangkat_golongan'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (!empty($validated['roles'])) {
            $user->assignRole($validated['roles']);
        }

        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        $roles = Role::pluck('name');
        $user->load('roles');
        return Inertia::render('admin/users/edit', [
            'user' => $user,
            'roles' => $roles,
            'userRoles' => $user->roles->pluck('name')
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:30',
            'password' => ['nullable', Rules\Password::defaults()],
            'nip' => 'nullable|string|max:50',
            'unit_kerja' => 'nullable|string|max:255',
            'jabatan' => 'nullable|string|max:255',
            'pangkat_golongan' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'roles' => 'array'
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'nip' => $validated['nip'] ?? null,
            'unit_kerja' => $validated['unit_kerja'] ?? null,
            'jabatan' => $validated['jabatan'] ?? null,
            'pangkat_golongan' => $validated['pangkat_golongan'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        if (isset($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')->with('error', 'Tidak dapat menghapus akun Anda sendiri.');
        }
        
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil dihapus.');
    }
}
