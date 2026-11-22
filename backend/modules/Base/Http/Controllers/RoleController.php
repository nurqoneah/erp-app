<?php

namespace Modules\Base\Http\Controllers;

use App\Core\Http\ListQuery;
use App\Core\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use App\Providers\AppServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Role::query()->withCount(['permissions', 'users']);
        $page = ListQuery::paginate($query, $request, ['name'], ['name'], 'name');

        return response()->json($page->through(fn (Role $role) => $this->present($role)));
    }

    public function show(Role $role): JsonResponse
    {
        $role->loadCount(['permissions', 'users']);

        return response()->json(['data' => $this->present($role, withPermissions: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $role = DB::transaction(function () use ($data) {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
            $role->syncPermissions($data['permissions'] ?? []);

            return $role;
        });
        $role->loadCount(['permissions', 'users']);

        return response()->json(['data' => $this->present($role, withPermissions: true)], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $this->guardSystemRole($role);
        $data = $this->validated($request, $role);

        DB::transaction(function () use ($role, $data) {
            $role->update(['name' => $data['name']]);
            $role->syncPermissions($data['permissions'] ?? []);
        });
        $role->loadCount(['permissions', 'users']);

        return response()->json(['data' => $this->present($role, withPermissions: true)]);
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->guardSystemRole($role);
        $role->delete();

        return response()->json(['message' => 'Role dihapus.']);
    }

    /** Nama-nama role untuk pilihan di form pengguna. */
    public function options(): JsonResponse
    {
        return response()->json(['data' => Role::query()->orderBy('name')->pluck('name')]);
    }

    /**
     * Semua permission dari modul aktif, dikelompokkan per modul.
     */
    public function permissions(ModuleManager $modules): JsonResponse
    {
        $groups = $modules->installed()
            ->map(fn ($module) => [
                'module' => $module->name,
                'label' => $module->label,
                'permissions' => collect($module->permissions())
                    ->map(fn (string $label, string $name) => ['name' => $name, 'label' => $label])
                    ->values(),
            ])
            ->filter(fn (array $group) => $group['permissions']->isNotEmpty())
            ->values();

        return response()->json(['data' => $groups]);
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role?->id),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ], [], ['name' => 'nama role']);
    }

    private function guardSystemRole(Role $role): void
    {
        abort_if(
            $role->name === AppServiceProvider::SUPER_ADMIN_ROLE,
            422,
            'Role super-admin adalah role sistem dan tidak bisa diubah atau dihapus.'
        );
    }

    private function present(Role $role, bool $withPermissions = false): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'is_system' => $role->name === AppServiceProvider::SUPER_ADMIN_ROLE,
            'permissions_count' => $role->permissions_count,
            'users_count' => $role->users_count,
            'permissions' => $this->when($withPermissions, fn () => $role->permissions->pluck('name')),
        ];
    }

    private function when(bool $condition, callable $value): mixed
    {
        return $condition ? $value() : null;
    }
}
