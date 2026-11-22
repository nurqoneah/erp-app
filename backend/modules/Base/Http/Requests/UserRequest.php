<?php

namespace Modules\Base\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', Password::min(8)],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama', 'password' => 'kata sandi', 'roles' => 'role'];
    }
}
