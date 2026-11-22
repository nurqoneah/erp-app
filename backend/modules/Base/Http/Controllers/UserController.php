<?php

namespace Modules\Base\Http\Controllers;

use App\Core\Http\ListQuery;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Base\Http\Requests\UserRequest;
use Modules\Base\Http\Resources\UserResource;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ListQuery::filterActive(User::query()->with('roles'), $request);

        return UserResource::collection(
            ListQuery::paginate($query, $request, ['name', 'email'], ['name', 'email', 'created_at'], 'name')
        );
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('roles'));
    }

    public function store(UserRequest $request): UserResource
    {
        $data = $request->validated();
        $this->guardSuperAdminRole($request, $data['roles'] ?? []);

        $user = DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ]);
            $user->syncRoles($data['roles'] ?? []);

            return $user;
        });

        return new UserResource($user->load('roles'));
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        $data = $request->validated();
        $isSelf = $request->user()->is($user);

        if ($isSelf && array_key_exists('is_active', $data) && ! $data['is_active']) {
            throw ValidationException::withMessages(['is_active' => 'Anda tidak bisa menonaktifkan akun sendiri.']);
        }

        if (array_key_exists('roles', $data)) {
            $this->guardSuperAdminRole($request, $data['roles'], $user);

            if ($isSelf && $user->hasRole(AppServiceProvider::SUPER_ADMIN_ROLE)
                && ! in_array(AppServiceProvider::SUPER_ADMIN_ROLE, $data['roles'], true)) {
                throw ValidationException::withMessages(['roles' => 'Anda tidak bisa mencabut role super-admin dari akun sendiri.']);
            }
        }

        DB::transaction(function () use ($user, $data) {
            $user->fill(collect($data)->only(['name', 'email', 'is_active'])->all());
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();

            if (array_key_exists('roles', $data)) {
                $user->syncRoles($data['roles']);
            }

            if (! $user->is_active) {
                $user->tokens()->delete();
            }
        });

        return new UserResource($user->load('roles'));
    }

    /**
     * Hanya super-admin yang boleh memberi / mencabut role super-admin.
     *
     * @param  list<string>  $roles
     */
    private function guardSuperAdminRole(Request $request, array $roles, ?User $target = null): void
    {
        $role = AppServiceProvider::SUPER_ADMIN_ROLE;
        $changes = in_array($role, $roles, true) !== (bool) $target?->hasRole($role);

        if ($changes && ! $request->user()->hasRole($role)) {
            abort(403, 'Hanya super-admin yang bisa mengatur role super-admin.');
        }
    }
}
