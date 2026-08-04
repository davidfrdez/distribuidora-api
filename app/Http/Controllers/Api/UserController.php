<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /** GET /api/admin/users */
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->when($request->filled('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->present($user));

        // Un administrador no debe ver la opción de crear superadmins: sólo
        // el propio superadmin puede otorgar ese rol.
        $availableRoles = $request->user()?->role->isSuperAdmin()
            ? UserRole::cases()
            : array_filter(UserRole::cases(), fn (UserRole $role) => ! $role->isSuperAdmin());

        return response()->json([
            'data' => $users,
            'roles' => collect($availableRoles)->map(fn (UserRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ])->values(),
        ]);
    }

    /** POST /api/admin/users */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', 'unique:user,email'],
            'password' => ['required', Password::min(8), 'max:100'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'phone' => ['nullable', 'string', 'max:30'],
            'documentNumber' => ['nullable', 'string', 'max:30'],
        ]);

        $this->assertCanAssignRole($request, UserRole::from($data['role']));

        // El cast `hashed` del modelo se encarga de la contraseña.
        $user = User::create($data + ['active' => true]);

        return response()->json(['data' => $this->present($user)], 201);
    }

    /** PUT /api/admin/users/{user} */
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'email', 'max:190', Rule::unique('user', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::min(8), 'max:100'],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'phone' => ['nullable', 'string', 'max:30'],
            'documentNumber' => ['nullable', 'string', 'max:30'],
            'active' => ['boolean'],
        ]);

        // Enviar la contraseña vacía significa "no la cambies".
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $this->assertCanManageTarget($request, $user);

        if (isset($data['role'])) {
            $this->assertCanAssignRole($request, UserRole::from($data['role']));
        }

        $this->assertNotLockingOutLastAdmin($user, $data);
        $this->assertNotLockingOutLastSuperAdmin($user, $data);

        $user->update($data);

        return response()->json(['data' => $this->present($user)]);
    }

    /** DELETE /api/admin/users/{user} — desactiva, no borra: el kardex lo referencia. */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json([
                'error' => 'CANNOT_DISABLE_SELF',
                'message' => 'No puedes desactivar tu propio usuario.',
            ], 422);
        }

        $this->assertCanManageTarget($request, $user);

        $this->assertNotLockingOutLastAdmin($user, ['active' => false]);
        $this->assertNotLockingOutLastSuperAdmin($user, ['active' => false]);

        $user->update(['active' => false]);

        return response()->json(['message' => 'Usuario desactivado.']);
    }

    /**
     * Un administrador (no superadmin) no puede otorgar el rol SUPERADMIN a
     * nadie, ni al crear ni al editar. Sólo un superadmin puede crear otros
     * superadmins.
     */
    private function assertCanAssignRole(Request $request, UserRole $targetRole): void
    {
        if (! $targetRole->isSuperAdmin()) {
            return;
        }

        abort_if(
            ! $request->user()?->role->isSuperAdmin(),
            403,
            'Sólo un superadministrador puede asignar el rol de superadministrador.',
        );
    }

    /**
     * Un administrador (no superadmin) no puede editar ni desactivar a un
     * usuario que ya es SUPERADMIN. El superadmin puede gestionar a cualquiera.
     */
    private function assertCanManageTarget(Request $request, User $target): void
    {
        if (! $target->role->isSuperAdmin()) {
            return;
        }

        abort_if(
            ! $request->user()?->role->isSuperAdmin(),
            403,
            'No tienes permiso para gestionar a un superadministrador.',
        );
    }

    /**
     * Impide quedarse sin ningún administrador activo, que dejaría el sistema
     * sin nadie capaz de gestionar usuarios ni autorizar operaciones.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertNotLockingOutLastAdmin(User $user, array $data): void
    {
        $losesAdmin = ($data['active'] ?? true) === false
            || (isset($data['role']) && $data['role'] !== UserRole::ADMINISTRADOR->value);

        if ($user->role !== UserRole::ADMINISTRADOR || ! $losesAdmin) {
            return;
        }

        $otherAdmins = User::where('role', UserRole::ADMINISTRADOR->value)
            ->where('active', true)
            ->whereKeyNot($user->id)
            ->count();

        abort_if(
            $otherAdmins === 0,
            422,
            'Es el último administrador activo: asigna otro antes de cambiarlo o desactivarlo.',
        );
    }

    /**
     * Impide quedarse sin ningún superadministrador activo: es la cuenta de
     * soporte del proveedor y el único capaz de gestionar a los administradores.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertNotLockingOutLastSuperAdmin(User $user, array $data): void
    {
        $losesSuperAdmin = ($data['active'] ?? true) === false
            || (isset($data['role']) && $data['role'] !== UserRole::SUPERADMIN->value);

        if ($user->role !== UserRole::SUPERADMIN || ! $losesSuperAdmin) {
            return;
        }

        $otherSuperAdmins = User::where('role', UserRole::SUPERADMIN->value)
            ->where('active', true)
            ->whereKeyNot($user->id)
            ->count();

        abort_if(
            $otherSuperAdmins === 0,
            422,
            'Es el último superadministrador activo: asigna otro antes de cambiarlo o desactivarlo.',
        );
    }

    /** @return array<string, mixed> */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'roleLabel' => $user->role->label(),
            'phone' => $user->phone,
            'documentNumber' => $user->documentNumber,
            'active' => $user->active,
            'lastLoginAt' => $user->lastLoginAt,
        ];
    }
}
