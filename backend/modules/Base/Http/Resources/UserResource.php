<?php

namespace Modules\Base\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** Sertakan daftar permission lengkap (untuk /auth/me). */
    public bool $withPermissions = false;

    public function withPermissions(): static
    {
        $this->withPermissions = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->when($this->withPermissions, fn () => $this->getAllPermissions()->pluck('name')),
            'created_at' => $this->created_at,
        ];
    }
}
