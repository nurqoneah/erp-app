<?php

namespace Modules\Membership\Providers;

use App\Core\Modules\ModuleProvider;
use Illuminate\Validation\Rule;
use Modules\Contacts\Models\Partner;

/**
 * Contoh perluasan modul lain: menambah field keanggotaan ke Kontak.
 */
class MembershipProvider extends ModuleProvider
{
    public const LEVELS = ['silver', 'gold', 'platinum'];

    protected string $module = 'Membership';

    public function boot(): void
    {
        $this->extendModel(Partner::class)
            // Closure: aturan unique perlu tahu kontak mana yang sedang diedit.
            ->field('member_number', fn () => [
                'nullable', 'string', 'max:30',
                Rule::unique('partners', 'member_number')->ignore(request()->route('partner')?->id),
            ], label: 'nomor member')
            ->field('member_level', ['nullable', Rule::in(self::LEVELS)], label: 'level member')
            ->field('member_since', ['nullable', 'date'], cast: 'date:Y-m-d', label: 'member sejak');
    }
}
